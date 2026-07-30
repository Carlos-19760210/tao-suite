# -*- coding: utf-8 -*-
import os
# CARGA FORNECEDORES — FC02000 (só os com NF de entrada) -> fornecedores (TAO)
# Match por nome com os já cadastrados (cotações): enriquece em vez de duplicar.
import fdb, json, urllib.request, re

BASE = r"C:\Users\carlo\FCertaSync"
SB   = "https://gclayesytzzpzkjvgede.supabase.co/rest/v1"
KEY  = os.environ.get("SUPABASE_KEY", "sb_secret_HpoqM6ujk2yD6la7KM3cuQ_pdWBK8jo")
CID  = "62f98634-77ff-42f4-acaf-8561d56583da"

def sb_req(path, method="GET", body=None):
    data = json.dumps(body).encode() if body is not None else None
    h = {"apikey": KEY, "Authorization": "Bearer " + KEY, "Content-Type": "application/json",
         "Prefer": "return=minimal"}
    r = urllib.request.Request(SB + path, data=data, method=method, headers=h)
    return urllib.request.urlopen(r).read().decode()

def sbq(path):
    h = {"apikey": KEY, "Authorization": "Bearer " + KEY}
    return json.loads(urllib.request.urlopen(urllib.request.Request(SB + path, headers=h)).read().decode())

def s(v): return v.strip() if isinstance(v, str) else v
def norm(t): return re.sub(r"[^A-Z0-9]", "", (t or "").upper())

con = fdb.connect(database=BASE + r"\fcerta_atual.ib", user="SYSDBA", password="masterkey",
                  fb_library_name=BASE + r"\fb25\fbembed.dll", charset="NONE")
cur = con.cursor()

exist = sbq(f"/fornecedores?cliente_id=eq.{CID}&select=id,nome,cnpj&limit=500")
print(f"existentes no TAO: {len(exist)}")
ja_cnpj = {e["cnpj"] for e in exist if e.get("cnpj")}

cur.execute("""SELECT f.FORNECID, f.NRCNPJ, f.NRINSCR, f.RAZAO, f.FANTA, f.ENDER, f.ENDNR,
                      f.BAIRR, f.NRCEP, f.MUNIC, f.UNFED, f.NRDDD, f.NRTEL
               FROM FC02000 f
               WHERE EXISTS (SELECT 1 FROM FC11000 n WHERE n.FORNECID = f.FORNECID)""")
ins, upd, skip = 0, 0, 0
for (fid, cnpj, ie, razao, fanta, ender, endnr, bairr, cep, munic, uf, ddd, tel) in cur.fetchall():
    cnpj  = re.sub(r"\D", "", s(cnpj) or "")
    razao = s(razao) or ""
    fanta = s(fanta) or ""
    nome  = fanta or razao
    if not nome: continue
    if cnpj and cnpj in ja_cnpj: skip += 1; continue

    dados = {
        "cnpj": cnpj or None, "razao_social": razao or None, "nome_fantasia": fanta or None,
        "inscr_estadual": s(ie) or None,
        "endereco": " ".join(x for x in [s(ender), s(endnr), s(bairr)] if x) or None,
        "cidade": s(munic) or None, "uf": s(uf) or None, "cep": s(cep) or None,
        "telefone": ((s(ddd) or "") + " " + (s(tel) or "")).strip() or None,
        "codigo_fc": str(fid),
    }
    # match por nome com cadastro manual das cotações (enriquece)
    alvo = None
    for e in exist:
        if e.get("cnpj"): continue
        ne, na, nr = norm(e["nome"]), norm(fanta), norm(razao)
        if ne and (ne in na or na in ne or (nr and (ne in nr or nr in ne))) and len(ne) >= 4:
            alvo = e; break
    if alvo:
        sb_req(f"/fornecedores?id=eq.{alvo['id']}&cliente_id=eq.{CID}", "PATCH", dados)
        alvo["cnpj"] = cnpj
        print(f"  enriquecido: {alvo['nome']} <- {nome} ({cnpj})")
        upd += 1
    else:
        dados.update({"cliente_id": CID, "nome": nome, "whatsapp": "", "ativo": True,
                      "obs": "Importado do FCerta (fornecedor com NF de entrada)"})
        sb_req("/fornecedores", "POST", dados)
        ins += 1
    if cnpj: ja_cnpj.add(cnpj)

print(f"\ninseridos: {ins} | enriquecidos: {upd} | já tinham CNPJ: {skip}")
con.close()
