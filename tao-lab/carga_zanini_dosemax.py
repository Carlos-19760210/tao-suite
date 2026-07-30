# -*- coding: utf-8 -*-
import os
# CARGA ZANINI DOSE MÁXIMA — FC71600.Z_DOSEMAXIMA (XOR 0x8A) -> ativos.dose_max_dia/unidade
# Match FC03000.Z_CDFARM. Só grava dose_max_dia numérico p/ doses ABSOLUTAS por dia
# (mg/g/mcg/dia); mg/kg/dia e afins não são absolutas → não entram no alerta numérico.
import fdb, json, urllib.request, re, time

BASE = r"C:\Users\carlo\FCertaSync"
SB   = "https://gclayesytzzpzkjvgede.supabase.co/rest/v1"
KEY  = os.environ.get("SUPABASE_KEY", "sb_secret_HpoqM6ujk2yD6la7KM3cuQ_pdWBK8jo")
CID  = "62f98634-77ff-42f4-acaf-8561d56583da"

def sb_req(path, method="GET", body=None):
    data = json.dumps(body).encode() if body is not None else None
    h = {"apikey": KEY, "Authorization": "Bearer " + KEY, "Content-Type": "application/json",
         "Prefer": "return=minimal"}
    return urllib.request.urlopen(urllib.request.Request(SB + path, data=data, method=method, headers=h)).read().decode()

def sbq(path, rng=None):
    h = {"apikey": KEY, "Authorization": "Bearer " + KEY}
    if rng: h["Range-Unit"] = "items"; h["Range"] = rng
    return json.loads(urllib.request.urlopen(urllib.request.Request(SB + path, headers=h)).read().decode())

def dec(b):
    raw = b if isinstance(b, bytes) else str(b).encode("latin-1", "replace")
    t = bytes(x ^ 0x8A for x in raw).decode("latin-1", "replace")
    return t.split("µ")[0].strip()   # µ (0xB5) marca fim do texto útil

# Dose ABSOLUTA por dia: "N mg/dia", "N g ao dia"... exclui /kg, /m2, por dose
ABS = re.compile(r"([\d]+(?:[.,]\d+)?)\s*(mg|mcg|g|ui)\s*/?\s*dia\b", re.I)
NEG = re.compile(r"/\s*kg|/\s*m|por\s+kg|kg/dia|/\s*dose", re.I)

con = fdb.connect(database=BASE + r"\fcerta_atual.ib", user="SYSDBA", password="masterkey",
                  fb_library_name=BASE + r"\fb25\fbembed.dll", charset="NONE")
cur = con.cursor()

# 1. Doses Zanini decodificadas por Z_CDFARM
cur.execute("SELECT Z_CDFARM, Z_DOSEMAXIMA FROM FC71600 WHERE Z_DOSEMAXIMA IS NOT NULL AND Z_DOSEMAXIMA<>''")
zdose = {}
for cd, blob in cur.fetchall():
    zdose[cd] = dec(blob)

# 2. Produtos FCerta → Z_CDFARM (só os que têm dose)
cur.execute("SELECT CDPRO, Z_CDFARM FROM FC03000 WHERE Z_CDFARM IS NOT NULL AND Z_CDFARM>0")
prod_zcd = {str(cdpro): zcd for cdpro, zcd in cur.fetchall() if zcd in zdose}
con.close()
print(f"produtos FCerta com dose Zanini: {len(prod_zcd)}")

# 3. Mapa codigo_fc → ativo no TAO
tao = {}
page = 0
while True:
    chunk = sbq(f"/ativos?cliente_id=eq.{CID}&select=id,codigo_fc&order=id.asc", rng=f"{page*1000}-{page*1000+999}")
    for a in chunk:
        if a.get("codigo_fc"): tao[str(a["codigo_fc"]).strip()] = a["id"]
    if len(chunk) < 1000: break
    page += 1

# 4. Parse + grava
UNI = {"mg": "mg", "mcg": "mcg", "g": "g", "ui": "UI"}
upd_num, upd_txt, sem_ativo = 0, 0, 0
for cdpro, zcd in prod_zcd.items():
    aid = tao.get(cdpro)
    if not aid: sem_ativo += 1; continue
    texto = zdose[zcd]
    patch = {}
    m = ABS.search(texto)
    if m and not NEG.search(texto):
        val = float(m.group(1).replace(".", "").replace(",", ".")) if "," in m.group(1) else float(m.group(1))
        patch["dose_max_dia"]      = val
        patch["dose_max_unidade"]  = UNI.get(m.group(2).lower(), m.group(2))
        upd_num += 1
    else:
        upd_txt += 1  # texto sem dose absoluta → não grava número (evita alerta errado)
        continue
    for tent in range(3):
        try:
            sb_req(f"/ativos?id=eq.{aid}&cliente_id=eq.{CID}", "PATCH", patch); break
        except Exception as e:
            if tent == 2: print(f"  falha {cdpro}: {e}"); break
            time.sleep(1)

print(f"\ndose_max_dia gravada (absoluta/dia): {upd_num}")
print(f"texto sem dose absoluta (não gravado, fica no FCerta): {upd_txt}")
print(f"sem ativo no TAO: {sem_ativo}")

# Confronto: quantos ativos ficaram com dose_max_dia
h = {"apikey": KEY, "Authorization": "Bearer " + KEY, "Prefer": "count=exact", "Range-Unit": "items", "Range": "0-0"}
r = urllib.request.urlopen(urllib.request.Request(f"{SB}/ativos?cliente_id=eq.{CID}&dose_max_dia=not.is.null&select=id", headers=h))
print(f"CONFRONTO: ativos c/ dose_max_dia no TAO = {int(r.headers['Content-Range'].split('/')[1])}")
