# -*- coding: utf-8 -*-
"""
Migra o LIVRO histórico de movimentação de controlados do FCerta (FC03120)
para sngpc_movimentos no TAO — marcado como HISTÓRICO:
  origem='fcerta_hist' + transmitido=true  → consultável (auditoria/BMPO retroativo),
  NÃO entra em novas transmissões da sombra (que só olha transmitido=false).

Idempotente: no --commit, apaga os origem='fcerta_hist' e reinsere.
Uso:  python carga_sngpc_historico.py            # dry (mostra volume)
      python carga_sngpc_historico.py --commit   # grava
"""
import fdb, urllib.request, json, sys, datetime

IB  = r"C:\Users\carlo\FCertaSync\fcerta_atual.ib"
LIB = r"C:\Users\carlo\FCertaSync\fb25\fbembed.dll"
SB  = "https://gclayesytzzpzkjvgede.supabase.co/rest/v1"
KEY = open(r"C:\Users\carlo\FCertaSync\supabase_key.txt").read().strip()
CID = "62f98634-77ff-42f4-acaf-8561d56583da"
DRY = "--commit" not in sys.argv

def s(v):
    if isinstance(v, (bytes, bytearray)): return v.decode("latin-1", "replace").strip()
    return "" if v is None else str(v).strip()

def sb_all(path):
    out = []; page = 0
    while True:
        req = urllib.request.Request(SB + path)
        for k, v in {"apikey": KEY, "Authorization": "Bearer " + KEY, "Range-Unit": "items",
                     "Range": f"{page*1000}-{page*1000+999}"}.items(): req.add_header(k, v)
        d = json.loads(urllib.request.urlopen(req).read()); out += d
        if len(d) < 1000: break
        page += 1
    return out

def sb_req(path, method="GET", body=None):
    req = urllib.request.Request(SB + path, method=method,
        data=json.dumps(body).encode() if body is not None else None)
    for k, v in {"apikey": KEY, "Authorization": "Bearer " + KEY, "Content-Type": "application/json",
                 "Prefer": "return=minimal"}.items(): req.add_header(k, v)
    try:
        return urllib.request.urlopen(req).read()
    except urllib.error.HTTPError as e:
        print("  PostgREST erro:", e.code, e.read().decode("utf-8", "replace")[:300])
        raise

# TAO: codigo_fc -> {id, dcb}
ativos = sb_all(f"/ativos?cliente_id=eq.{CID}&select=id,codigo_fc,dcb")
por_cod = {str(a["codigo_fc"]).strip(): a for a in ativos if a.get("codigo_fc")}

con = fdb.connect(database=IB, user="SYSDBA", password="masterkey", fb_library_name=LIB, charset="NONE")
cur = con.cursor()
cur.execute("""select m.CDPRO, m.TPMOV, m.PORTA, m.QUANT, m.UNIDA, m.ANORF, m.MESRF, m.DIARF,
                      m.CDFNR, m.NRNOTIF, m.NRREG, p.CDDCB, p.REGISTROPRODUTO
               from FC03120 m left join FC03000 p on p.CDPRO=m.CDPRO
               order by m.ANORF, m.MESRF, m.DIARF""")
rows = cur.fetchall()

TPMAP = {"E": "entrada", "S": "saida"}
regs = []; sem_ativo = 0
for r in rows:
    cd = s(r[0]); tp = TPMAP.get(s(r[1]), None)
    if not tp: continue
    a = por_cod.get(cd)
    if not a: sem_ativo += 1
    try:
        dt = f"{int(r[5]):04d}-{int(r[6]):02d}-{int(r[7]):02d}"
        datetime.date(int(r[5]), int(r[6]), int(r[7]))
    except Exception:
        dt = None
    q = float(r[3] or 0)
    reg = {
        "cliente_id": CID, "tipo": tp,
        "ativo_id": a["id"] if a else None,
        "dcb": s(r[11]) or (a.get("dcb") if a else None) or None,
        "classe_sngpc": s(r[2]) or None,
        "registro_ms": s(r[12]) or None,
        "quantidade": q, "unidade": s(r[4]) or None,
        "dt_movimento": dt,
        "fornecedor_cnpj": (s(r[8]) if tp == "entrada" and s(r[8]) not in ("", "0") else None),
        "nr_notificacao": s(r[9]) or None,
        "origem": "fcerta_hist", "transmitido": True,
    }
    regs.append(reg)

con.close()
anos = {}
for x in regs:
    y = (x["dt_movimento"] or "?")[:4]; anos[y] = anos.get(y, 0) + 1
print(f"FC03120 -> {len(regs)} movimentos p/ migrar (sem ativo no TAO: {sem_ativo})")
print("por ano:", dict(sorted(anos.items())))
print("amostra:", {k: regs[0][k] for k in ("tipo","dcb","classe_sngpc","quantidade","unidade","dt_movimento")} if regs else "—")

if DRY:
    print("\nDRY-RUN: nada gravado (use --commit).")
else:
    print("\napagando históricos anteriores (origem=fcerta_hist)...")
    sb_req(f"/sngpc_movimentos?cliente_id=eq.{CID}&origem=eq.fcerta_hist", "DELETE")
    ok = 0
    for i in range(0, len(regs), 500):
        sb_req("/sngpc_movimentos", "POST", regs[i:i+500]); ok += len(regs[i:i+500])
        print(f"  inseridos {ok}/{len(regs)}")
    print(f"GRAVADO: {ok} movimentos históricos de controlados.")
