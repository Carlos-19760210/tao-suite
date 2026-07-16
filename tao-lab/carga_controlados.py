# -*- coding: utf-8 -*-
# CARGA CONTROLADOS — FC03000.INDSNGPC='S' -> ativos.controlado=true + classe_sngpc(=PORTA) + registro_ms
# Universo: matéria-prima (GRUPO='M') presente na filial 1 => 49 ativos controlados no TAO.
# Idempotente: PATCH direto (reexecutar não duplica). Match por ativos.codigo_fc = FC03000.CDPRO.
# Carga LEVE (~49 PATCHs). Regra: só rodar com OK do Carlos.
import fdb, json, urllib.request, sys

BASE = r"C:\Users\carlo\FCertaSync"
SB   = "https://gclayesytzzpzkjvgede.supabase.co/rest/v1"
KEY  = "sb_secret_HpoqM6ujk2yD6la7KM3cuQ_pdWBK8jo"
CID  = "62f98634-77ff-42f4-acaf-8561d56583da"
DRY  = "--commit" not in sys.argv   # padrão = simulação; --commit para gravar

def s(v): return v.strip() if isinstance(v, str) else v

def sb_patch(path, body):
    data = json.dumps(body).encode()
    h = {"apikey": KEY, "Authorization": "Bearer " + KEY, "Content-Type": "application/json",
         "Prefer": "return=minimal"}
    return urllib.request.urlopen(urllib.request.Request(SB + path, data=data, method="PATCH", headers=h)).read()

def sbq(path, rng=None):
    h = {"apikey": KEY, "Authorization": "Bearer " + KEY}
    if rng: h["Range-Unit"] = "items"; h["Range"] = rng
    return json.loads(urllib.request.urlopen(urllib.request.Request(SB + path, headers=h)).read().decode())

# 1. Controlados MP na filial 1 (do FCerta)
con = fdb.connect(database=BASE + r"\fcerta_atual.ib", user="SYSDBA", password="masterkey",
                  fb_library_name=BASE + r"\fb25\fbembed.dll", charset="NONE")
cur = con.cursor()
cur.execute("""SELECT DISTINCT p.CDPRO, p.PORTA, p.DESCR, p.CDREGISTROMS
               FROM FC03000 p JOIN FC03100 e ON e.CDPRO=p.CDPRO
               WHERE p.INDSNGPC='S' AND p.GRUPO='M' AND e.CDFIL=1""")
EXCLUI_CLASSE = {"AM"}   # Magis NÃO manipula antibióticos (antimicrobianos RDC 471) — decisão Carlos
ctrl = {}
for cdpro, porta, descr, regms in cur.fetchall():
    if s(porta) in EXCLUI_CLASSE:
        continue
    ctrl[str(cdpro)] = {"classe": s(porta), "descr": s(descr), "regms": s(regms)}
con.close()
print(f"FCerta: {len(ctrl)} matérias-primas controladas (GRUPO=M, filial 1, sem antibióticos)")

# 2. Mapa codigo_fc -> ativo no TAO
tao = {}
page = 0
while True:
    chunk = sbq(f"/ativos?cliente_id=eq.{CID}&select=id,codigo_fc,nome,controlado,classe_sngpc&order=id.asc",
                rng=f"{page*1000}-{page*1000+999}")
    if not chunk: break
    for a in chunk:
        if a.get("codigo_fc"): tao[str(a["codigo_fc"]).strip()] = a
    if len(chunk) < 1000: break
    page += 1
print(f"TAO: {len(tao)} ativos com codigo_fc")

# 3. Cruzamento + PATCH
achados, faltantes, jamarc = [], [], 0
for cdpro, info in ctrl.items():
    a = tao.get(cdpro)
    if not a:
        faltantes.append((cdpro, info["descr"]))
        continue
    if a.get("controlado") and a.get("classe_sngpc") == info["classe"]:
        jamarc += 1
    achados.append((a, info))

print(f"\nCasados: {len(achados)}  |  já marcados corretos: {jamarc}  |  não achados no TAO: {len(faltantes)}")
if faltantes:
    print("  (não estão no TAO — provavelmente fora da filial 1 ou descontinuados):")
    for cd, d in faltantes[:20]: print("   -", cd, d)

print("\n" + ("== SIMULAÇÃO (use --commit para gravar) ==" if DRY else "== GRAVANDO =="))
n = 0
for a, info in achados:
    body = {"controlado": True, "classe_sngpc": info["classe"]}
    if info["regms"]: body["registro_ms"] = info["regms"]
    marca = "" if (a.get("controlado") and a.get("classe_sngpc")==info["classe"]) else " *novo/alterado*"
    print(f"  [{info['classe'] or '-':3}] {a['nome'][:40]:40} (fc {a['codigo_fc']}){marca}")
    if not DRY:
        sb_patch(f"/ativos?id=eq.{a['id']}", body)
    n += 1
print(f"\n{'Simulados' if DRY else 'Gravados'}: {n} ativos controlados")
