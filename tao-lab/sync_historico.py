#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
SYNC HISTÓRICO FCerta → TAO Neo (hist_clientes / hist_formulas / hist_formulas_itens)

INCREMENTAL e IDEMPOTENTE: pode ser acionada sempre que houver um backup novo do
FCerta — só complementa o que falta, nunca duplica nem apaga.

Fluxo:
  1. Localiza o arquivo de banco (ALTERDB.ib) no diretório de backup configurado
     (padrão: pasta DB/ do FCerta no OneDrive; mude com --db-dir ou --db-file).
  2. Se o arquivo mudou desde a última sync (mtime/tamanho em state.json), copia
     para a área de trabalho local (WORK_DIR) — nunca abre o original.
  3. Clientes:  insere os CDCLI que ainda não existem no TAO.
  4. Fórmulas:  janela incremental = fórmulas FCerta com DTCAD >= (última data no
     TAO − 30 dias); insere as chaves (NRRQU, SERIER) que faltam, com seus itens.
     Use --full para reconciliar o histórico inteiro (compara todas as chaves).
  5. Confronto FCerta × TAO no final (contagens + amostra recente).

Uso:
  python sync_historico.py                     # incremental, fonte padrão
  python sync_historico.py --full              # reconciliação completa
  python sync_historico.py --db-dir "D:\\backups\\fcerta"
  python sync_historico.py --db-file "D:\\ALTERDB.ib" --force

Requisitos: python + fdb (pip install fdb) + Firebird 2.5 embedded x64 em
C:\\Users\\carlo\\FCertaSync\\fb25 (zip R2_5_9 x64_embed do GitHub do Firebird).
"""
import argparse, datetime, json, os, shutil, sys, time, uuid, urllib.request

# ── Configuração padrão ──────────────────────────────────────────────────────
DB_DIR_PADRAO = r"C:\Users\carlo\OneDrive\Área de Trabalho\MagisTAO\Fcerta\Fcerta\DB"
DB_ARQ_PADRAO = "ALTERDB.ib"
WORK_DIR      = r"C:\Users\carlo\FCertaSync"
FB_DLL        = os.path.join(WORK_DIR, "fb25", "fbembed.dll")

SB  = "https://gclayesytzzpzkjvgede.supabase.co/rest/v1"
KEY = "sb_secret_HpoqM6ujk2yD6la7KM3cuQ_pdWBK8jo"
CID = "62f98634-77ff-42f4-acaf-8561d56583da"   # tenant Magis-TAO

JANELA_DIAS = 30      # margem da janela incremental sobre a última dt_cadastro

# ── Supabase helpers ─────────────────────────────────────────────────────────
def sb_req(path, method="GET", body=None, prefer="return=minimal", rng=None):
    data = json.dumps(body).encode() if body is not None else None
    h = {"apikey": KEY, "Authorization": "Bearer " + KEY,
         "Content-Type": "application/json", "Prefer": prefer}
    if rng: h["Range-Unit"] = "items"; h["Range"] = rng
    r = urllib.request.Request(SB + path, data=data, method=method, headers=h)
    return urllib.request.urlopen(r)

def sbq(path, rng=None):
    return json.loads(sb_req(path, rng=rng).read().decode())

def sb_count(path_sem_select):
    resp = sb_req(path_sem_select + "&select=id", prefer="count=exact", rng="0-0")
    return int(resp.headers["Content-Range"].split("/")[1])

def sb_all(path, page_size=1000):
    """Pagina um GET até esgotar."""
    out, page = [], 0
    while True:
        chunk = sbq(path, rng=f"{page*page_size}-{page*page_size+page_size-1}")
        out.extend(chunk)
        if len(chunk) < page_size: return out
        page += 1

def post_batches(table, rows, size=500, label=""):
    for i in range(0, len(rows), size):
        for tent in range(3):
            try:
                sb_req(f"/{table}", "POST", rows[i:i+size]); break
            except Exception as e:
                if tent == 2: raise
                print(f"  retry {table} lote {i}: {e}"); time.sleep(2)
        if (i // size) % 20 == 0 and len(rows) > size:
            print(f"  {label or table}: {min(i+size, len(rows))}/{len(rows)}")

# ── Conversões FCerta (charset NONE: CHAR vem como str com padding) ──────────
def s(v):
    if isinstance(v, bytes): v = v.decode("latin-1", "replace")
    return v.strip() if isinstance(v, str) else v

def d(v):   return v.isoformat() if v else None
def num(v): return float(v) if v is not None else None

def serier_int(v):
    """SERIER é CHAR alfanumérico: 0-9 depois A=10..Z=35 (verificado: sempre 1 char)."""
    if isinstance(v, int): return v
    t = (s(v) or "0").upper()
    if t.isdigit(): return int(t)
    if len(t) == 1 and "A" <= t <= "Z": return 10 + ord(t) - ord("A")
    return 0

# ── Sync ─────────────────────────────────────────────────────────────────────
def main():
    ap = argparse.ArgumentParser(description="Sync incremental do histórico FCerta → TAO")
    ap.add_argument("--db-dir",  default=DB_DIR_PADRAO, help="diretório onde está o backup (ALTERDB.ib)")
    ap.add_argument("--db-file", default=None, help="caminho completo do .ib (ignora --db-dir)")
    ap.add_argument("--full",    action="store_true", help="reconcilia o histórico inteiro (todas as chaves)")
    ap.add_argument("--force",   action="store_true", help="recopia o banco mesmo sem mudança de mtime/tamanho")
    args = ap.parse_args()

    fonte = args.db_file or os.path.join(args.db_dir, DB_ARQ_PADRAO)
    if not os.path.isfile(fonte):
        sys.exit(f"ERRO: arquivo de banco não encontrado: {fonte}")
    if not os.path.isfile(FB_DLL):
        sys.exit(f"ERRO: Firebird embedded não encontrado em {FB_DLL} (zip R2_5_9 x64_embed)")

    os.makedirs(WORK_DIR, exist_ok=True)
    state_path = os.path.join(WORK_DIR, "state.json")
    state = {}
    if os.path.isfile(state_path):
        with open(state_path) as f: state = json.load(f)

    st   = os.stat(fonte)
    fkey = f"{st.st_mtime_ns}:{st.st_size}"
    work_ib = os.path.join(WORK_DIR, "work.ib")
    if args.force or state.get("fonte") != fonte or state.get("fkey") != fkey or not os.path.isfile(work_ib):
        print(f"copiando {fonte} ({st.st_size/1e9:.2f} GB) -> {work_ib} ...")
        shutil.copyfile(fonte, work_ib)
    else:
        print(f"banco inalterado desde a última sync ({datetime.datetime.fromtimestamp(st.st_mtime):%d/%m/%Y %H:%M}) — usando cópia existente")

    import fdb
    con = fdb.connect(database=work_ib, user="SYSDBA", password="masterkey",
                      fb_library_name=FB_DLL, charset="NONE")
    cur = con.cursor()

    # ── 1. Clientes: insere os que faltam ───────────────────────────────────
    print("== clientes ==")
    cli_map = {c["cdcli"]: c["id"] for c in
               sb_all(f"/hist_clientes?cliente_id=eq.{CID}&select=id,cdcli&order=cdcli.asc")}
    cur.execute("""SELECT c.CDCLI, c.NOMECLI, c.DTNAS, c.EMAIL, c.OBSERV
                   FROM FC07000 c WHERE EXISTS (SELECT 1 FROM FC12100 r WHERE r.CDCLI = c.CDCLI)""")
    novos_cli = []
    for cdcli, nome, dtnas, email, obs in cur.fetchall():
        nome = s(nome)
        if not nome or cdcli in cli_map: continue
        cid = str(uuid.uuid4())
        cli_map[cdcli] = cid
        novos_cli.append({"id": cid, "cliente_id": CID, "cdcli": cdcli, "nome": nome,
                          "dt_nascimento": d(dtnas), "email": s(email) or None,
                          "observacoes": s(obs) or None})
    print(f"  no TAO: {len(cli_map) - len(novos_cli)} | novos: {len(novos_cli)}")
    post_batches("hist_clientes", novos_cli, label="hist_clientes")

    # ── 2. Fórmulas: chaves que faltam (janela incremental ou --full) ───────
    print("== formulas ==")
    if args.full:
        corte_sql = ""
        chaves = sb_all(f"/hist_formulas?cliente_id=eq.{CID}&select=nrrqu,serier&order=nrrqu.asc")
    else:
        ult = sbq(f"/hist_formulas?cliente_id=eq.{CID}&select=dt_cadastro&order=dt_cadastro.desc&limit=1")
        corte = (datetime.date.fromisoformat(ult[0]["dt_cadastro"]) -
                 datetime.timedelta(days=JANELA_DIAS)) if ult else datetime.date(1900, 1, 1)
        corte_sql = f" WHERE DTCAD >= '{corte.isoformat()}'"
        chaves = sb_all(f"/hist_formulas?cliente_id=eq.{CID}&dt_cadastro=gte.{corte.isoformat()}"
                        f"&select=nrrqu,serier&order=nrrqu.asc")
        print(f"  janela incremental: DTCAD >= {corte} (use --full p/ reconciliar tudo)")
    existentes = {(c["nrrqu"], c["serier"]) for c in chaves}

    cur.execute(f"""SELECT NRRQU, SERIER, CDCLI, NRORC, DTCAD, DTRET, VOLUME, UNIVOL, QTCONT,
                           POSOL, NOMEPA, PRCOBR, PRCUSTO, INDREPET, DTVAL
                    FROM FC12100{corte_sql}""")
    form_map, novas = {}, []
    for (nrrqu, serier, cdcli, nrorc, dtcad, dtret, vol, univol, qtcont,
         posol, nomepa, prcobr, prcusto, indrep, dtval) in cur.fetchall():
        sr = serier_int(serier)
        if (nrrqu, sr) in existentes: continue
        fid = str(uuid.uuid4())
        form_map[(nrrqu, sr)] = fid
        novas.append({
            "id": fid, "cliente_id": CID,
            "hist_cliente_id": cli_map.get(cdcli),
            "cdcli": cdcli or None, "nrrqu": nrrqu, "serier": sr, "nrorc": nrorc or None,
            "dt_cadastro": d(dtcad), "dt_retirada": d(dtret),
            "volume": num(vol), "univol": s(univol) or None, "qt_potes": int(qtcont or 1),
            "posologia": s(posol) or None, "nome_paciente": s(nomepa) or None,
            "preco_cobrado": num(prcobr), "preco_custo": num(prcusto),
            "ind_repet": s(indrep) == "S", "dt_validade": d(dtval),
        })
    print(f"  novas fórmulas: {len(novas)}")
    post_batches("hist_formulas", novas, label="hist_formulas")

    # ── 3. Itens das fórmulas novas (C=componente, E=embalagem, P=cápsula) ──
    print("== itens ==")
    itens = []
    if form_map:
        nrrqus = sorted({k[0] for k in form_map})
        for i in range(0, len(nrrqus), 500):
            lote = ",".join(str(n) for n in nrrqus[i:i+500])
            cur.execute(f"""SELECT NRRQU, SERIER, ITEMID, TPCMP, CDPRO, DESCR, QUANT, UNIDA, QTREAL, INDQSP
                            FROM FC12110 WHERE TPCMP IN ('C','E','P') AND NRRQU IN ({lote})""")
            for nrrqu, serier, itemid, tpcmp, cdpro, descr, quant, unida, qtreal, indqsp in cur.fetchall():
                fid = form_map.get((nrrqu, serier_int(serier)))
                if not fid: continue   # série já existia no TAO
                itens.append({
                    "formula_id": fid, "tpcmp": s(tpcmp),
                    "codigo_fc": str(cdpro) if cdpro else None,
                    "descr": s(descr) or None, "dose": num(quant), "unidade": s(unida) or None,
                    "qt_real": num(qtreal), "is_qsp": s(indqsp) == "S", "ordem": int(itemid or 0),
                })
    print(f"  novos itens: {len(itens)}")
    post_batches("hist_formulas_itens", itens, size=800, label="hist_itens")

    # ── Confronto ────────────────────────────────────────────────────────────
    print("\n== CONFRONTO FCerta x TAO ==")
    cur.execute("SELECT COUNT(*) FROM FC12100"); fc_form = cur.fetchone()[0]
    cur.execute("SELECT COUNT(*) FROM FC12110 WHERE TPCMP IN ('C','E','P')"); fc_it = cur.fetchone()[0]
    cur.execute("""SELECT COUNT(*) FROM FC07000 c
                   WHERE EXISTS (SELECT 1 FROM FC12100 r WHERE r.CDCLI = c.CDCLI)
                   AND c.NOMECLI IS NOT NULL AND c.NOMECLI <> ''"""); fc_cli = cur.fetchone()[0]
    print(f"  clientes:  FCerta {fc_cli}  | TAO {sb_count(f'/hist_clientes?cliente_id=eq.{CID}')}")
    print(f"  formulas:  FCerta {fc_form}  | TAO {sb_count(f'/hist_formulas?cliente_id=eq.{CID}')}")
    print(f"  itens:     FCerta {fc_it}  | TAO {sb_count('/hist_formulas_itens?id=not.is.null')}")
    cur.execute("SELECT FIRST 1 NRRQU, SERIER, DTCAD FROM FC12100 ORDER BY DTCAD DESC, NRRQU DESC")
    r = cur.fetchone()
    print(f"  última FCerta: req {r[0]}/{serier_int(r[1])} de {r[2]}")
    t = sbq(f"/hist_formulas?cliente_id=eq.{CID}&select=nrrqu,serier,dt_cadastro&order=dt_cadastro.desc,nrrqu.desc&limit=1")
    print(f"  última TAO:    req {t[0]['nrrqu']}/{t[0]['serier']} de {t[0]['dt_cadastro']}" if t else "  TAO vazio")
    con.close()

    state.update({"fonte": fonte, "fkey": fkey,
                  "ultima_sync": datetime.datetime.now().isoformat(timespec="seconds"),
                  "novos": {"clientes": len(novos_cli), "formulas": len(novas), "itens": len(itens)}})
    with open(state_path, "w") as f: json.dump(state, f, indent=2)
    print(f"\nSYNC CONCLUÍDA — novos: {len(novos_cli)} clientes, {len(novas)} fórmulas, {len(itens)} itens")
    print(f"estado salvo em {state_path}")

if __name__ == "__main__":
    main()
