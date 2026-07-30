#!/usr/bin/env python3
# -*- coding: utf-8 -*-
import os
"""
SYNC ATIVOS — FCerta (FC03000/FC03100/FC15110) → Supabase `ativos` + `tipos_capsula`.
Sucessor SEGURO do sincronizar_tao.ps1 para rodadas RECORRENTES de equalização:

  • NOVO codigo_fc  → INSERT completo (payload igual ao do PS1 original).
  • JÁ EXISTE       → PATCH apenas dos campos que VÊM do FCerta e SOMENTE se mudaram.
                      NUNCA toca: controlado, excipiente_padrao, margem_padrao,
                      nome_alt, classe_terapeutica, teor_pct, markup_preco,
                      fator_diluicao, ativo_puro_id (config feita no TAO).
  • AUSENTE no FCerta → só RELATÓRIO (não desativa nada — decisão humana).
  • Cápsulas (FC0H000/FC0H100) → upsert on_conflict (cliente_id,tipo,numero).

Idempotente. Uso:
  python sync_ativos.py --dry            # só relata (não grava nada)
  python sync_ativos.py                  # executa
  python sync_ativos.py --db-file "D:\\outro.ib"
"""
import fdb, json, urllib.request, urllib.parse, urllib.error, argparse, re, sys, datetime

DB_PADRAO = r"C:\Users\carlo\FCertaSync\fcerta_atual.ib"
FB_DLL    = r"C:\Users\carlo\FCertaSync\fb25\fbembed.dll"
SB        = "https://gclayesytzzpzkjvgede.supabase.co/rest/v1"
KEY       = os.environ.get("SUPABASE_KEY", "sb_secret_HpoqM6ujk2yD6la7KM3cuQ_pdWBK8jo")
CID       = "62f98634-77ff-42f4-acaf-8561d56583da"

UNID_MP  = {"G":"g","GR":"g","KG":"g","L":"g","MG":"mg","MEQ":"mg","MCG":"mcg","UG":"mcg",
            "NG":"mcg","UI":"UI","IU":"UI","U":"UI","UFC":"UFC","BLH":"BLH","ML":"g",
            "UN":"mg","CAP":"mg","CAPS":"mg"}
UNID_EMB = {"UN":"un","CAP":"un","CAPS":"un","PC":"un","PAR":"un","KIT":"un","RL":"un",
            "ENV":"un","G":"g","MG":"mg","ML":"g","L":"g"}

# Campos seguros para ATUALIZAR num ativo existente (espelham o FCerta):
# nome_original fica FORA do update (cosmético; só entra no INSERT de linha nova)
CAMPOS_UPDATE = ["nome","unidade","unidade_padrao","estoque_atual","em_estoque",
                 "preco_compra","preco_custo","custo_por_unidade","preco_venda","categoria",
                 "principio_ativo","densidade","fator_correcao","diluicao","teor","dcb",
                 "dose_min","uni_dose_min","dose_max","uni_dose_max","observacoes","concentracao"]
# Fiscais: vêm do FCerta mas só PREENCHEM VAZIOS no TAO (não sobrescrevem o que o contador revisou).
CAMPOS_FISCAIS = ["ncm","cst_pis","cst_cofins","icms_cod_fcerta","cest","gtin"]

def sb_req(path, method="GET", body=None, headers=None):
    h = {"apikey": KEY, "Authorization": "Bearer " + KEY, "Content-Type": "application/json",
         "Prefer": "return=minimal"}
    if headers: h.update(headers)
    data = json.dumps(body).encode() if body is not None else None
    r = urllib.request.Request(SB + path, data=data, method=method, headers=h)
    return urllib.request.urlopen(r).read().decode()

def sb_all(path):
    out, page = [], 0
    while True:
        h = {"apikey": KEY, "Authorization": "Bearer " + KEY,
             "Range-Unit": "items", "Range": f"{page*1000}-{page*1000+999}"}
        r = urllib.request.Request(SB + path, headers=h)
        ch = json.loads(urllib.request.urlopen(r).read().decode())
        out += ch
        if len(ch) < 1000: return out
        page += 1

def dec(v):
    return v.decode("latin-1", "replace").strip() if isinstance(v, (bytes, bytearray)) else \
           (v.strip() if isinstance(v, str) else v)

def num(v, default=None):
    try:
        f = float(v)
        return f
    except (TypeError, ValueError):
        return default

def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--dry", action="store_true", help="não grava nada, só relata")
    ap.add_argument("--db-file", default=DB_PADRAO)
    args = ap.parse_args()

    agora = datetime.datetime.now(datetime.timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ")
    con = fdb.connect(database=args.db_file, user="SYSDBA", password="masterkey",
                      fb_library_name=FB_DLL, charset="NONE")
    cur = con.cursor()

    # ── concentrações UFC/UI (FC15110, NRORC mais recente por produto) ──────
    cur.execute("""SELECT CDPRO, MAX(NRORC) FROM FC15110
                   WHERE CDFIL=1 AND (QTBLH>0 OR QTMLH>0 OR QTUFC>0 OR QTUI>0) GROUP BY CDPRO""")
    max_nrorc = {str(r[0]): r[1] for r in cur.fetchall()}
    cur.execute("""SELECT CDPRO, NRORC, COALESCE(QTBLH,0), COALESCE(QTMLH,0),
                          COALESCE(QTUFC,0), COALESCE(QTUI,0) FROM FC15110
                   WHERE CDFIL=1 AND (QTBLH>0 OR QTMLH>0 OR QTUFC>0 OR QTUI>0)""")
    conc_map = {}
    for cdpro, nrorc, blh, mlh, ufc, ui in cur.fetchall():
        cdpro = str(cdpro)
        if max_nrorc.get(cdpro) != nrorc: continue
        if   blh: conc_map[cdpro] = float(blh) * 1e9
        elif mlh: conc_map[cdpro] = float(mlh) * 1e6
        elif ufc: conc_map[cdpro] = float(ufc)
        elif ui:  conc_map[cdpro] = float(ui)

    # ── extração FC03000 × FC03100 (mesma query do sincronizar_tao.ps1) ─────
    def extrai(grupo):
        cur.execute(f"""SELECT p.CDPRO, TRIM(REPLACE(p.DESCR,'@','')), TRIM(p.DESCR),
                               COALESCE(TRIM(p.UNIDA),''), e.ESTAT, p.PRCOM, e.PRCOMCTB, p.PRVEN,
                               COALESCE(TRIM(p.CATEGORIA),''), COALESCE(TRIM(p.PRINCIPIOATIVO),''),
                               p.DENSIDADE, p.FATOR, COALESCE(TRIM(p.CDDCB),''),
                               p.DOMIN, COALESCE(TRIM(p.UNIDMIN),''), p.DOMAX, COALESCE(TRIM(p.UNIDM),''),
                               COALESCE(TRIM(p.OBSCOMPO),''), p.DILUICAO, p.TEOR,
                               COALESCE(TRIM(p.CLFISC),''), COALESCE(TRIM(p.CDSITPIS),''),
                               COALESCE(TRIM(p.CDSITCOFINS),''), COALESCE(TRIM(p.INDTRIBISS),''),
                               COALESCE(TRIM(p.CDICM),''), COALESCE(TRIM(p.CDCEST),''), COALESCE(TRIM(p.CDGTIN),'')
                        FROM FC03000 p JOIN FC03100 e ON e.CDPRO=p.CDPRO AND e.CDFIL=1
                        WHERE p.SITUA='A' AND p.GRUPO='{grupo}'""")
        return cur.fetchall()

    def monta(r, grupo):
        cdpro = str(r[0]).strip()
        unid  = dec(r[3]) or ""
        umap  = UNID_MP if grupo == "M" else UNID_EMB
        upad  = umap.get(unid.upper(), "un" if grupo == "E" else "mg")
        dilui = num(r[18], 1.0) or 1.0
        teor  = num(r[19], 100.0) or 100.0
        if dilui <= 0: dilui = 1.0
        if teor  <= 0: teor  = 100.0
        estat = num(r[4])
        custo = num(r[6])
        return {
            "cliente_id": CID, "codigo_fc": cdpro,
            "nome": dec(r[1]), "nome_original": dec(r[2]), "grupo": grupo,
            "unidade": unid, "unidade_padrao": upad,
            "estoque_atual": estat, "em_estoque": bool(estat and estat > 0),
            "preco_compra": num(r[5]), "preco_custo": custo,
            "custo_por_unidade": custo if custo is not None else 0,
            "preco_venda": num(r[7], 0) or 0,
            "categoria": dec(r[8]), "principio_ativo": dec(r[9]),
            "densidade": num(r[10], 1.0) or 1.0, "fator_correcao": num(r[11], 1.0) or 1.0,
            "diluicao": dilui, "teor": teor, "dcb": dec(r[12]),
            "dose_min": num(r[13]), "uni_dose_min": dec(r[14]) or None,
            "dose_max": num(r[15]), "uni_dose_max": dec(r[16]) or None,
            "observacoes": dec(r[17]), "concentracao": conc_map.get(cdpro),
            # fiscal (do FCerta) → atributo do ativo; só preenche vazios no PATCH (não sobrescreve contador)
            "ncm": dec(r[20]) or None, "cst_pis": dec(r[21]) or None, "cst_cofins": dec(r[22]) or None,
            "ind_iss": (dec(r[23]).upper() == "S"), "icms_cod_fcerta": dec(r[24]) or None,
            "cest": dec(r[25]) or None, "gtin": dec(r[26]) or None,
            "ativo": True, "sincronizado_em": agora, "atualizado_em": agora,
        }

    fc_rows = [(monta(r, "M")) for r in extrai("M")] + [(monta(r, "E")) for r in extrai("E")]
    fc_por_cod = {a["codigo_fc"]: a for a in fc_rows}
    print(f"FCerta: {len(fc_rows)} produtos ativos (M+E) extraídos")

    # ── estado atual no TAO ──────────────────────────────────────────────────
    # Detecta se a migration fiscal (colunas em ativos) já rodou; se não, sincroniza sem o fiscal.
    fiscal_ok = True
    try:
        sel = "id,codigo_fc,ativo," + ",".join(CAMPOS_UPDATE + CAMPOS_FISCAIS)
        tao = sb_all(f"/ativos?cliente_id=eq.{CID}&select={sel}&order=id.asc")
    except urllib.error.HTTPError as e:
        if e.code != 400: raise
        fiscal_ok = False
        print("AVISO: colunas fiscais ausentes em `ativos` — rode migration_ativo_fiscal_v1.sql. Sync fiscal PULADO.")
        sel = "id,codigo_fc,ativo," + ",".join(CAMPOS_UPDATE)
        tao = sb_all(f"/ativos?cliente_id=eq.{CID}&select={sel}&order=id.asc")
    if not fiscal_ok:  # remove os campos fiscais dos payloads (insert e patch)
        for a in fc_por_cod.values():
            for c in CAMPOS_FISCAIS + ["ind_iss"]: a.pop(c, None)
    tao_por_cod = {str(a["codigo_fc"]).strip(): a for a in tao if a.get("codigo_fc")}
    print(f"TAO   : {len(tao)} ativos ({len(tao_por_cod)} com codigo_fc)")

    def mudou(novo, velho):
        if novo is None: return False                    # nunca sobrescreve com null
        if isinstance(novo, bool): return bool(velho) != novo
        if isinstance(novo, (int, float)):
            try: b = float(velho)
            except (TypeError, ValueError): return True  # TAO vazio, FCerta tem valor
            return abs(float(novo) - b) > 1e-6 * max(1.0, abs(float(novo)))
        s = str(novo).strip()
        if s == "": return False                         # não apaga texto com vazio
        return s != str(velho or "").strip()

    # defaults que o sincronizar_tao.ps1 enviava — só no INSERT (linha nova)
    DEFAULTS_INSERT = {"nome_alt": "", "margem_padrao": None, "classe_terapeutica": None,
                       "controlado": False, "fator_perda": 1.0, "excipiente_padrao": None,
                       "dose_minima_padrao": None, "dose_maxima_padrao": None}

    inserts, patches, reativar = [], [], 0
    for cod, novo in fc_por_cod.items():
        atual = tao_por_cod.get(cod)
        if not atual:
            inserts.append({**DEFAULTS_INSERT, **novo}); continue
        upd = {}
        for c in CAMPOS_UPDATE:
            if mudou(novo.get(c), atual.get(c)):
                upd[c] = novo.get(c)
        # fiscais: só preenche o que está VAZIO no TAO (preserva revisão do contador)
        if fiscal_ok:
            for c in CAMPOS_FISCAIS:
                nv = novo.get(c)
                if nv not in (None, "") and str(atual.get(c) or "").strip() == "":
                    upd[c] = nv
        if atual.get("ativo") is False:
            upd["ativo"] = True; reativar += 1
        if upd:
            upd["sincronizado_em"] = agora; upd["atualizado_em"] = agora
            patches.append((atual["id"], cod, upd))

    ausentes = [a for c, a in tao_por_cod.items() if c not in fc_por_cod and a.get("ativo")]

    print(f"\nPLANO: inserir {len(inserts)} novos | atualizar {len(patches)} | "
          f"reativar {reativar} | AUSENTES no FCerta (só relatório): {len(ausentes)}")
    if inserts:
        print("Novos (amostra 15):")
        for a in inserts[:15]: print(f"  + [{a['grupo']}] {a['codigo_fc']} {a['nome']}")
    if patches:
        print("Atualizações (amostra 10):")
        for _id, cod, upd in patches[:10]:
            print(f"  ~ {cod} {tao_por_cod[cod].get('nome','')[:40]}: "
                  f"{ {k: v for k, v in upd.items() if k not in ('sincronizado_em','atualizado_em')} }")
    if ausentes:
        print("Ausentes no FCerta — ativos no TAO que NÃO vieram na extração (avaliar manualmente):")
        for a in ausentes[:20]: print(f"  ? {a['codigo_fc']} {a.get('nome','')}")
        if len(ausentes) > 20: print(f"  ... e mais {len(ausentes)-20}")

    if args.dry:
        print("\nDRY-RUN: nada gravado."); con.close(); return

    # ── grava ────────────────────────────────────────────────────────────────
    for i in range(0, len(inserts), 200):
        sb_req("/ativos", "POST", inserts[i:i+200])
    print(f"INSERTs OK: {len(inserts)}")
    err = 0
    for _id, cod, upd in patches:
        try:
            sb_req(f"/ativos?id=eq.{_id}", "PATCH", upd)
        except Exception as e:
            err += 1; print(f"  ERRO PATCH {cod}: {e}")
    print(f"PATCHes OK: {len(patches)-err} | erros: {err}")

    # ── cápsulas (upsert idempotente) ────────────────────────────────────────
    cur.execute("""SELECT TRIM(h.DESCRICAO), c.NUMERO, c.VOLINTERNO, c.PESOVAZIO, c.CDPRO
                   FROM FC0H100 c JOIN FC0H000 h ON h.IDTIPOCAP=c.IDTIPOCAP
                   WHERE c.INDSTATUS='A' ORDER BY h.DESCRICAO, c.NUMERO""")
    caps, seen = [], set()
    for tipo, numero, vol, peso, cdpro in cur.fetchall():
        tipo = dec(tipo); numero = str(numero).strip()
        if vol is None or (tipo, numero) in seen: continue
        seen.add((tipo, numero))
        caps.append({"cliente_id": CID, "tipo": tipo, "numero": numero, "vol_ul": float(vol),
                     "peso_vazio_mg": float(peso) if peso is not None else None,
                     "cdpro_fc": str(cdpro).strip() if cdpro is not None else None,
                     "ativo": True, "sincronizado_em": agora})
    if caps:
        sb_req("/tipos_capsula?on_conflict=cliente_id,tipo,numero", "POST", caps,
               headers={"Prefer": "resolution=merge-duplicates,return=minimal"})
    print(f"Cápsulas upsert: {len(caps)}")
    con.close()
    print("SYNC ATIVOS CONCLUÍDO.")

if __name__ == "__main__":
    main()
