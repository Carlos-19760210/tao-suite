#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
SYNC EXCIPIENTES — replica a associação ativo->excipiente do FCerta para o TAO.
Fonte: FC99999 WHERE ARGUMENTO='EXCEP'  (SUBARGUM=produto, PARAMETRO=excipiente).
Preenche ativos.excipiente_id no TAO (mapeando por codigo_fc). Idempotente.

Uso:
  python sync_excipientes.py --dry            # só relata
  python sync_excipientes.py --commit         # grava
  python sync_excipientes.py --db "C:\\...\\fcerta.ib"
"""
import fdb, json, urllib.request, urllib.error, argparse
FB_DLL = r"C:\Users\carlo\FCertaSync\fb25\fbembed.dll"
DB_PADRAO = r"C:\Users\carlo\FCertaSync\fcerta_28.ib"
SB  = "https://gclayesytzzpzkjvgede.supabase.co/rest/v1"
KEY = "sb_secret_HpoqM6ujk2yD6la7KM3cuQ_pdWBK8jo"
CID = "62f98634-77ff-42f4-acaf-8561d56583da"

def s(v): return str(v).strip() if v is not None else ""
def norm(cod):
    """normaliza codigo_fc para comparar dos dois lados (remove zeros à esquerda; ignora sujeira)."""
    t = s(cod).split()[0] if s(cod) else ""   # '31059 1,3' -> '31059'
    try: return str(int(t))
    except (ValueError, TypeError): return ""

def sb_all(path):
    out, page = [], 0
    while True:
        h={"apikey":KEY,"Authorization":"Bearer "+KEY,"Range-Unit":"items","Range":f"{page*1000}-{page*1000+999}"}
        ch=json.loads(urllib.request.urlopen(urllib.request.Request(SB+path,headers=h)).read().decode())
        out+=ch
        if len(ch)<1000: return out
        page+=1
def sb_patch(path, body):
    r=urllib.request.Request(SB+path, data=json.dumps(body).encode(), method="PATCH",
        headers={"apikey":KEY,"Authorization":"Bearer "+KEY,"Content-Type":"application/json","Prefer":"return=minimal"})
    return urllib.request.urlopen(r).status

def main():
    ap=argparse.ArgumentParser()
    ap.add_argument("--db", default=DB_PADRAO); ap.add_argument("--commit", action="store_true")
    ap.add_argument("--dry", action="store_true")
    args=ap.parse_args()
    DRY = not args.commit

    # 1) FCerta: associações produto->excipiente
    con=fdb.connect(database=args.db, user="SYSDBA", password="masterkey", fb_library_name=FB_DLL, charset="NONE")
    cur=con.cursor()
    cur.execute("select rdb$field_name from rdb$relation_fields where rdb$relation_name='FC99999' order by rdb$field_position")
    cols=[r[0].strip() for r in cur.fetchall()]; cur.close()
    cur=con.cursor(); cur.execute("select * from FC99999")
    assoc=[]   # (produto_cod, excip_cod)
    while True:
        b=cur.fetchmany(3000)
        if not b: break
        for r in b:
            d={cols[i]:s(r[i]) for i in range(len(r))}
            if d.get("ARGUMENTO")=="EXCEP" and d.get("SUBARGUM") and d.get("PARAMETRO"):
                p, e = norm(d["SUBARGUM"]), norm(d["PARAMETRO"])
                if p and e: assoc.append((p, e))
    cur.close(); con.close()
    print(f"FCerta: {len(assoc)} associações ativo->excipiente (ARGUMENTO=EXCEP)")

    # 2) TAO: mapa codigo_fc(normalizado) -> ativo
    ativos=sb_all(f"/ativos?cliente_id=eq.{CID}&select=id,codigo_fc,nome,excipiente_id")
    por_cod={}
    for a in ativos:
        k=norm(a.get("codigo_fc"))
        if k: por_cod[k]=a
    print(f"TAO: {len(ativos)} ativos ({len(por_cod)} com codigo_fc)")

    # 3) montar patches (só onde muda; ativo e excipiente têm de existir no TAO)
    patches=[]; sem_ativo=0; sem_excip=0; ja_ok=0
    for prod, exc in assoc:
        a=por_cod.get(prod); e=por_cod.get(exc)
        if not a: sem_ativo+=1; continue
        if not e: sem_excip+=1; continue
        if s(a.get("excipiente_id"))==s(e["id"]): ja_ok+=1; continue
        patches.append((a, e))

    print(f"\nPLANO: atualizar {len(patches)} ativos | já corretos {ja_ok} | "
          f"ativo ausente no TAO {sem_ativo} | excipiente ausente {sem_excip}")
    for a,e in patches[:12]:
        print(f"  ~ {a['nome'][:26]:<26} (fc {a.get('codigo_fc')}) -> excip {e['nome'][:24]} (fc {e.get('codigo_fc')})")
    if len(patches)>12: print(f"  ... +{len(patches)-12}")

    if DRY:
        print("\nDRY-RUN: nada gravado (use --commit).")
        return
    err=0
    for a,e in patches:
        try: sb_patch(f"/ativos?id=eq.{a['id']}", {"excipiente_id": e["id"]})
        except urllib.error.HTTPError as ex: err+=1; print("  ERRO", a.get("codigo_fc"), ex.read()[:120])
    print(f"\nGRAVADO: {len(patches)-err} atualizados | {err} erros")

if __name__=="__main__":
    main()
