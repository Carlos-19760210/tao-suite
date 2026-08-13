# -*- coding: utf-8 -*-
# Read-only: acha tabelas FCerta com registros de HOJE (data-alvo) via colunas de data.
import sys, io
sys.stdout.reconfigure(encoding='utf-8', errors='replace')
import migra
cfg = migra.load_config(sys.argv[1])
HOJE = sys.argv[2] if len(sys.argv) > 2 else "2026-08-12"
con = migra.fb_open(cfg); cur = con.cursor()

# colunas de tipo DATE(12)/TIMESTAMP(35) em tabelas de usuário
cur.execute("""
select trim(rf.rdb$relation_name), trim(rf.rdb$field_name), f.rdb$field_type
from rdb$relation_fields rf
join rdb$fields f on f.rdb$field_name = rf.rdb$field_source
join rdb$relations r on r.rdb$relation_name = rf.rdb$relation_name
where r.rdb$view_blr is null and coalesce(r.rdb$system_flag,0)=0
  and f.rdb$field_type in (12,35)
order by 1,2
""")
cols = cur.fetchall()
from collections import defaultdict
bytab = defaultdict(list)
for t,c,ty in cols: bytab[migra._s(t)].append(migra._s(c))
print("tabelas com coluna de data:", len(bytab))

achados=[]
for t, cs in bytab.items():
    for c in cs:
        try:
            cur.execute(f"select count(*) from {t} where cast({c} as date) = '{HOJE}'")
            n = cur.fetchone()[0]
            if n and n>0:
                achados.append((n,t,c))
        except Exception:
            pass
achados.sort(reverse=True)
print(f"\n== tabelas com registros em {HOJE} ==")
for n,t,c in achados:
    print(f"  {t:<12} {c:<14} {n}")
con.close()
