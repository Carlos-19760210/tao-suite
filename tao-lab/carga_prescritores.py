# -*- coding: utf-8 -*-
import os
# CARGA PRESCRITORES — FC04000 (+FC04400 endereço) -> prescritores (TAO)
# Idempotente: pula codigo_fc já carregado. Requer migration_v3.
import fdb, json, urllib.request, time

BASE = r"C:\Users\carlo\FCertaSync"
SB   = "https://gclayesytzzpzkjvgede.supabase.co/rest/v1"
KEY  = os.environ.get("SUPABASE_KEY", "sb_secret_HpoqM6ujk2yD6la7KM3cuQ_pdWBK8jo")
CID  = "62f98634-77ff-42f4-acaf-8561d56583da"

def sb_req(path, method="GET", body=None, rng=None):
    data = json.dumps(body).encode() if body is not None else None
    h = {"apikey": KEY, "Authorization": "Bearer " + KEY, "Content-Type": "application/json",
         "Prefer": "return=minimal"}
    if rng: h["Range-Unit"] = "items"; h["Range"] = rng
    r = urllib.request.Request(SB + path, data=data, method=method, headers=h)
    return urllib.request.urlopen(r).read().decode()

def sbq(path, rng=None):
    h = {"apikey": KEY, "Authorization": "Bearer " + KEY}
    if rng: h["Range-Unit"] = "items"; h["Range"] = rng
    return json.loads(urllib.request.urlopen(urllib.request.Request(SB + path, headers=h)).read().decode())

def s(v): return v.strip() if isinstance(v, str) else v

# PFCRM (tipo do registro) — 1/2/3/9 confirmados pelo perfil da manipulação;
# códigos não mapeados ficam como 'REG <cod>' p/ ajuste via CRUD
TIPO_REG = {"1": "CRM", "2": "CRO", "3": "CRMV", "9": "CRN"}

con = fdb.connect(database=BASE + r"\fcerta_atual.ib", user="SYSDBA", password="masterkey",
                  fb_library_name=BASE + r"\fb25\fbembed.dll", charset="NONE")
cur = con.cursor()

# já carregados (idempotência)
ja = set()
page = 0
while True:
    chunk = sbq(f"/prescritores?cliente_id=eq.{CID}&select=codigo_fc&order=codigo_fc.asc",
                rng=f"{page*1000}-{page*1000+999}")
    ja.update(c["codigo_fc"] for c in chunk if c.get("codigo_fc"))
    if len(chunk) < 1000: break
    page += 1
print(f"já no TAO: {len(ja)}")

# endereço (1º de FC04400 por prescritor)
cur.execute("""SELECT PFCRM, UFCRM, NRCRM, ENDER, ENDNR, BAIRR, MUNIC, UNFED, NRCEP, NRDDD, NRTEL
               FROM FC04400""")
ends = {}
for pf, ufc, crm, ender, endnr, bairr, munic, uf, cep, ddd, tel in cur.fetchall():
    k = (s(pf), s(ufc), crm)
    if k in ends: continue
    ends[k] = {
        "endereco": " ".join(x for x in [s(ender), s(endnr), s(bairr)] if x) or None,
        "cidade": s(munic) or None, "uf": s(uf) or None, "cep": s(cep) or None,
        "telefone": ((s(ddd) or "") + " " + (s(tel) or "")).strip() or None,
    }

cur.execute("""SELECT PFCRM, UFCRM, NRCRM, NOMEMED, TPSEX, NRDDDCEL, NRCEL, OBSERV
               FROM FC04000""")
rows = []
for pf, ufc, crm, nome, sexo, dddc, cel, obs in cur.fetchall():
    nome = s(nome)
    if not nome or not crm: continue
    pf_s, uf_s = s(pf) or "", s(ufc) or ""
    cod = f"{pf_s}|{uf_s}|{crm}"
    if cod in ja: continue
    ja.add(cod)
    end = ends.get((pf_s, uf_s, crm), {})
    rows.append({
        "cliente_id":    CID,
        "tratamento":    "Dra" if s(sexo) == "F" else "Dr",
        "nome":          nome,
        "tipo_registro": TIPO_REG.get(pf_s, f"REG {pf_s}" if pf_s else None),
        "nr_registro":   str(crm),
        "uf_registro":   uf_s or None,
        "celular":       ((s(dddc) or "") + " " + (s(cel) or "")).strip() or None,
        "telefone":      end.get("telefone"),
        "endereco":      end.get("endereco"),
        "cidade":        end.get("cidade"),
        "uf":            end.get("uf"),
        "cep":           end.get("cep"),
        "obs":           s(obs) or None,
        "codigo_fc":     cod,
    })
print(f"a inserir: {len(rows)}")
for i in range(0, len(rows), 500):
    for tent in range(3):
        try:
            sb_req("/prescritores", "POST", rows[i:i+500]); break
        except Exception as e:
            if tent == 2: raise
            print(f"  retry {i}: {e}"); time.sleep(2)
    print(f"  {min(i+500, len(rows))}/{len(rows)}")

# confronto
cur.execute("SELECT COUNT(*) FROM FC04000 WHERE NOMEMED IS NOT NULL AND NOMEMED<>'' AND NRCRM IS NOT NULL")
fc = cur.fetchone()[0]
h = {"apikey": KEY, "Authorization": "Bearer " + KEY, "Prefer": "count=exact", "Range-Unit": "items", "Range": "0-0"}
r = urllib.request.urlopen(urllib.request.Request(f"{SB}/prescritores?cliente_id=eq.{CID}&select=id", headers=h))
print(f"\nCONFRONTO: FCerta {fc} | TAO {int(r.headers['Content-Range'].split('/')[1])}")
con.close()
