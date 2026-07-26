# -*- coding: utf-8 -*-
"""
Motor de migração FCerta -> TAO Neo (reutilizável, multi-tenant / SaaS).
Fonte PLUGÁVEL: 'fcerta' (Firebird nativo) ou 'arquivos' (CSV/XLSX disponibilizados).
Config por tenant (NÃO comitar config.json com credenciais — só config.exemplo.json).

Uso:
  python migra.py <config.json> restore
  python migra.py <config.json> sync-fiscal
  python migra.py <config.json> sngpc-gen  <YYYY-MM-DD> <YYYY-MM-DD> [saida.xml]
  python migra.py <config.json> sngpc-shadow <YYYY-MM-DD> <YYYY-MM-DD> <xml_do_fcerta>

Requer: fdb (fcerta), openpyxl (arquivos .xlsx). urllib/stdlib para o Supabase.
"""
import sys, os, json, subprocess, urllib.request
from collections import Counter
from xml.sax.saxutils import escape
import xml.etree.ElementTree as ET

# ── infra ─────────────────────────────────────────────────────────────────────
def load_config(p):
    with open(p, encoding="utf-8") as f: return json.load(f)

def supa(cfg): return cfg["supabase"]["url"], cfg["supabase"]["key"], cfg["cliente_id"]

def supa_post(cfg, path, rows, prefer):
    url, key, _ = supa(cfg)
    req = urllib.request.Request(url + "/rest/v1/" + path, data=json.dumps(rows).encode(),
        method="POST", headers={"apikey": key, "Authorization": "Bearer " + key,
        "Content-Type": "application/json", "Prefer": prefer})
    return urllib.request.urlopen(req).status

def supa_count(cfg, table):
    url, key, cli = supa(cfg)
    req = urllib.request.Request(url + "/rest/v1/%s?select=id&cliente_id=eq.%s" % (table, cli),
        headers={"apikey": key, "Authorization": "Bearer " + key, "Prefer": "count=exact", "Range": "0-0"})
    return urllib.request.urlopen(req).headers.get("content-range", "?")

def fb_open(cfg):
    import fdb
    fc = cfg["source"]["fcerta"]
    return fdb.connect(database=fc["ib"], user="SYSDBA", password="masterkey",
                       fb_library_name=fc["fb_lib"], charset="NONE")

def _s(v):
    if isinstance(v, (bytes, bytearray)): return v.decode("latin-1", "replace").strip()
    return "" if v is None else str(v).strip()

# ── FONTES (adapters) → modelo canônico de PRODUTO ────────────────────────────
# canônico: {codigo, descricao, grupo, ncm, cest, gtin, cst_pis, cst_cofins, ind_iss, icms_cod}
def _prod_fcerta(cfg):
    con = fb_open(cfg); cur = con.cursor()
    cur.execute("select CDPRO,GRUPO,CLFISC,CDCEST,CDGTIN,CDICM,CDSITPIS,CDSITCOFINS,INDTRIBISS,DESCR "
                "from FC03000 where SITUA='A' and CLFISC is not null and trim(CLFISC)<>''")
    out = []
    for cdpro, grupo, ncm, cest, gtin, cdicm, pis, cof, iss, descr in cur.fetchall():
        out.append(dict(codigo=_s(cdpro), grupo=_s(grupo), ncm=_s(ncm), cest=_s(cest), gtin=_s(gtin),
                        cst_pis=_s(pis), cst_cofins=_s(cof), ind_iss=(_s(iss).upper() == "S"),
                        icms_cod=_s(cdicm), descricao=_s(descr)))
    con.close(); return out

def _prod_arquivos(cfg):
    a = cfg["source"]["arquivos"]; path = a["produtos_csv"]; m = a["map"]
    def norm(row):
        g = lambda k: _s(row.get(m.get(k, ""), ""))
        return dict(codigo=g("codigo"), grupo=g("grupo"), ncm=g("ncm"), cest=g("cest"), gtin=g("gtin"),
                    cst_pis=g("cst_pis"), cst_cofins=g("cst_cofins"),
                    ind_iss=(g("ind_iss").upper() in ("S", "SIM", "1", "TRUE")),
                    icms_cod=g("icms_cod"), descricao=g("descricao"))
    rows = []
    if path.lower().endswith(".xlsx"):
        import openpyxl
        wb = openpyxl.load_workbook(path, read_only=True, data_only=True); ws = wb.active
        it = ws.iter_rows(values_only=True); hdr = [str(c or "") for c in next(it)]
        for r in it: rows.append(dict(zip(hdr, r)))
    else:
        import csv
        with open(path, encoding="utf-8-sig", newline="") as f:
            for r in csv.DictReader(f, delimiter=a.get("sep", ";")): rows.append(r)
    return [norm(r) for r in rows if _s(r.get(m.get("codigo", ""), "")) and _s(r.get(m.get("ncm", ""), ""))]

def produtos_fonte(cfg):
    tipo = cfg["source"]["tipo"]
    if tipo == "fcerta":   return _prod_fcerta(cfg)
    if tipo == "arquivos": return _prod_arquivos(cfg)
    raise SystemExit("source.tipo inválido: " + tipo)

# ── CARGA (loader) → TAO ──────────────────────────────────────────────────────
def load_fiscal(cfg, prods):
    cli = cfg["cliente_id"]
    payload = [dict(cliente_id=cli, codigo_fc=p["codigo"], descricao=p["descricao"], grupo=p["grupo"] or None,
                    ncm=p["ncm"] or None, cest=p["cest"] or None, gtin=p["gtin"] or None,
                    cst_pis=p["cst_pis"] or None, cst_cofins=p["cst_cofins"] or None,
                    ind_iss=p["ind_iss"], icms_cod_fcerta=p["icms_cod"] or None) for p in prods]
    print("antes:", supa_count(cfg, "fiscal_produtos"))
    ok = 0
    for i in range(0, len(payload), 500):
        supa_post(cfg, "fiscal_produtos?on_conflict=cliente_id,codigo_fc",
                  payload[i:i+500], "resolution=merge-duplicates,return=minimal")
        ok += len(payload[i:i+500])
    print("upsertados:", ok, "| depois:", supa_count(cfg, "fiscal_produtos"))

# ── SNGPC (fcerta): gerar + comparar (shadow) ─────────────────────────────────
def _q6(v):
    try: return "%.6f" % float(str(v).replace(",", "."))
    except: return "0.000000"
def _qi(v):
    try: return str(int(float(str(v).replace(",", "."))))
    except: return "0"

def sngpc_gerar(cfg, DI, DF):
    con = fb_open(cfg); cur = con.cursor()
    def q(sql):
        cur.execute(sql); return [{k[0]: _s(val) for k, val in zip(cur.description, row)} for row in cur.fetchall()]
    hdr = q("select first 1 NRCNPJ,NRCPFTRANSMISSOR from FC01000")[0]
    sa = q("select p.CDDCB CODINS,s.TPRECMED,s.NRNOTIF,s.DTPRESCR,s.NOMEPRESCR,s.NRCRM,s.CONSELHO,s.UFCONSELHO,"
           "s.USOMED,s.NOMECOMP,s.TPDOCCOMP,s.NRDOCCOMP,s.UFORGAO,s.UFDOCCOMP,s.INDUSOPROL,s.NRLOT,s.CNPJFORNEC,"
           "s.QUANT,s.TPUNIDA,s.TPUNIDAFAR,s.QUANTFAR,s.DTVENDA from FC99S22 s join FC03000 p on p.CDPRO=s.CDPRO "
           "where s.DTVENDA between '%s' and '%s' order by s.DTVENDA" % (DI, DF))
    pe = q("select p.CDDCB CODINS,s.NRLOT,s.CNPJFORNEC,s.QUANT,s.TPUNIDA,s.TPPERDA,s.DTPERDA "
           "from FC99S24 s join FC03000 p on p.CDPRO=s.CDPRO where s.DTPERDA between '%s' and '%s'" % (DI, DF))
    con.close()
    e = escape
    X = ['<?xml version="1.0" encoding="ISO-8859-1"?>', '<mensagemSNGPC xmlns="urn:sngpc-schema"><cabecalho>',
         '<cnpjEmissor>%s</cnpjEmissor><cpfTransmissor>%s</cpfTransmissor>' % (e(hdr["NRCNPJ"]), e(hdr["NRCPFTRANSMISSOR"])),
         '<dataInicio>%s</dataInicio><dataFim>%s</dataFim></cabecalho><corpo><medicamentos/><insumos>' % (DI, DF)]
    for r in sa:
        X.append('<saidaInsumoVendaAoConsumidor><tipoReceituarioInsumo>%s</tipoReceituarioInsumo><numeroNotificacaoInsumo>%s</numeroNotificacaoInsumo><dataPrescricaoInsumo>%s</dataPrescricaoInsumo><prescritorInsumo><nomePrescritor>%s</nomePrescritor><numeroRegistroProfissional>%s</numeroRegistroProfissional><conselhoProfissional>%s</conselhoProfissional><UFConselho>%s</UFConselho></prescritorInsumo><usoInsumo>%s</usoInsumo><compradorInsumo><nomeComprador>%s</nomeComprador><tipoDocumento>%s</tipoDocumento><numeroDocumento>%s</numeroDocumento><orgaoExpedidor>%s</orgaoExpedidor><UFEmissaoDocumento>%s</UFEmissaoDocumento></compradorInsumo><substanciaInsumoVendaAoConsumidor><usoProlongado>%s</usoProlongado><insumoVendaAoConsumidor><codigoInsumo>%s</codigoInsumo><numeroLoteInsumo>%s</numeroLoteInsumo><insumoCNPJFornecedor>%s</insumoCNPJFornecedor></insumoVendaAoConsumidor><quantidadeDeInsumoPorUnidadeFarmacotecnica>%s</quantidadeDeInsumoPorUnidadeFarmacotecnica><unidadeDeMedidaDoInsumo>%s</unidadeDeMedidaDoInsumo><unidadeFarmacotecnica>%s</unidadeFarmacotecnica><quantidadeDeUnidadesFarmacotecnicas>%s</quantidadeDeUnidadesFarmacotecnicas></substanciaInsumoVendaAoConsumidor><dataVendaInsumo>%s</dataVendaInsumo></saidaInsumoVendaAoConsumidor>' % (
            e(r["TPRECMED"]), e(r["NRNOTIF"]), e(r["DTPRESCR"]), e(r["NOMEPRESCR"]), e(r["NRCRM"]), e(r["CONSELHO"]), e(r["UFCONSELHO"]), e(r["USOMED"]), e(r["NOMECOMP"]), e(r["TPDOCCOMP"]), e(r["NRDOCCOMP"]), e(r["UFORGAO"]), e(r["UFDOCCOMP"]), e(r["INDUSOPROL"]), e(r["CODINS"]), e(r["NRLOT"]), e(r["CNPJFORNEC"]), _q6(r["QUANT"]), e(r["TPUNIDA"]), e(r["TPUNIDAFAR"]), _qi(r["QUANTFAR"]), e(r["DTVENDA"])))
    for r in pe:
        X.append('<saidaInsumoPerda><motivoPerdaInsumo>%s</motivoPerdaInsumo><substanciaInsumoPerda><insumoPerda><codigoInsumo>%s</codigoInsumo><numeroLoteInsumo>%s</numeroLoteInsumo><insumoCNPJFornecedor>%s</insumoCNPJFornecedor></insumoPerda><quantidadeInsumoPerda>%s</quantidadeInsumoPerda><tipoUnidadePerda>%s</tipoUnidadePerda></substanciaInsumoPerda><dataPerdaInsumo>%s</dataPerdaInsumo><insumoCNPJFornecedor>%s</insumoCNPJFornecedor></saidaInsumoPerda>' % (
            e(r["TPPERDA"]), e(r["CODINS"]), e(r["NRLOT"]), e(r["CNPJFORNEC"]), _q6(r["QUANT"]), e(r["TPUNIDA"]), e(r["DTPERDA"]), e(r["CNPJFORNEC"])))
    X.append("</insumos></corpo></mensagemSNGPC>")
    return "\n".join(X), len(sa), len(pe)

def _recs(path, tag):
    out = []
    for node in ET.parse(path).getroot().iter():
        if node.tag.split("}")[-1] == tag:
            d = {}
            for leaf in node.iter():
                if len(list(leaf)) == 0: d[leaf.tag.split("}")[-1]] = (leaf.text or "").strip()
            out.append(tuple(sorted(d.items())))
    return out

# ── comandos ──────────────────────────────────────────────────────────────────
def cmd_restore(cfg):
    fc = cfg["source"]["fcerta"]
    if os.path.exists(fc["ib"]): os.remove(fc["ib"])
    print("restaurando", fc["ibk"], "->", fc["ib"], "...")
    r = subprocess.run([fc["gbak"], "-c", "-user", "SYSDBA", "-password", "masterkey", fc["ibk"], fc["ib"]])
    print("exit:", r.returncode, "| tamanho:", os.path.getsize(fc["ib"]) if os.path.exists(fc["ib"]) else "?")

def cmd_sync_fiscal(cfg):
    p = produtos_fonte(cfg); print("produtos da fonte (%s):" % cfg["source"]["tipo"], len(p))
    load_fiscal(cfg, p)

def cmd_sngpc_gen(cfg, DI, DF, out=None):
    xml, ns, npd = sngpc_gerar(cfg, DI, DF)
    out = out or ("SNGPC_%s_%s_%s.xml" % (cfg["tenant"], DI, DF))
    open(out, "w", encoding="iso-8859-1", errors="replace").write(xml)
    print("gerado:", out, "| saidas:", ns, "| perdas:", npd)

def cmd_sngpc_shadow(cfg, DI, DF, fcerta_xml):
    out = "shadow_%s_%s_%s.xml" % (cfg["tenant"], DI, DF)
    cmd_sngpc_gen(cfg, DI, DF, out)
    for tag in ("saidaInsumoVendaAoConsumidor", "saidaInsumoPerda"):
        G = Counter(_recs(out, tag)); S = Counter(_recs(fcerta_xml, tag))
        print("[%s] gerado=%d fcerta=%d | identicos=%d | so_gerado=%d | so_fcerta=%d" % (
            tag, sum(G.values()), sum(S.values()), sum((G & S).values()), sum((G - S).values()), sum((S - G).values())))

def main():
    if len(sys.argv) < 3:
        print(__doc__); raise SystemExit(1)
    cfg = load_config(sys.argv[1]); cmd = sys.argv[2]; a = sys.argv[3:]
    if   cmd == "restore":      cmd_restore(cfg)
    elif cmd == "sync-fiscal":  cmd_sync_fiscal(cfg)
    elif cmd == "sngpc-gen":    cmd_sngpc_gen(cfg, a[0], a[1], a[2] if len(a) > 2 else None)
    elif cmd == "sngpc-shadow": cmd_sngpc_shadow(cfg, a[0], a[1], a[2])
    else: print("comando desconhecido:", cmd); print(__doc__)

if __name__ == "__main__":
    main()
