# -*- coding: utf-8 -*-
# Read-only: lista tabelas do FCerta e procura as de estoque/fórmula/ordem/pesagem.
import sys, io
sys.stdout.reconfigure(encoding='utf-8', errors='replace')
import migra
cfg = migra.load_config(sys.argv[1])
con = migra.fb_open(cfg); cur = con.cursor()

cur.execute("select rdb$relation_name from rdb$relations "
            "where rdb$view_blr is null and rdb$system_flag=0 order by 1")
tabs=[migra._s(r[0]) for r in cur.fetchall()]
print("total tabelas usuário:", len(tabs))

import re
pat=re.compile(r'MOV|ESTOQ|LOTE|FORM|ORDEM|ORDM|PESAG|MANIP|RECEIT|SAID|ENTRA|REQUI', re.I)
alvo=[t for t in tabs if pat.search(t)]
print("\n== candidatas (estoque/fórmula/ordem/pesagem) ==")
for t in alvo:
    try:
        cur.execute("select count(*) from "+t); n=cur.fetchone()[0]
    except Exception as e:
        n="?"
    print(f"  {t:<14} {n}")
con.close()
