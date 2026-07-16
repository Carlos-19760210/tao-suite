#!/usr/bin/env python3
# TESTE DE OURO — motor farmacotécnico TAO vs pesagens REAIS do FCerta (FC12110.QTPESA)
# 100% local, somente leitura da cópia do banco.
import fdb
from collections import Counter

BASE = r"C:\Users\carlo\AppData\Local\Temp\claude\C--Users-carlo\a6c38159-6860-477a-83dc-c9e69ae557f1\scratchpad"
con = fdb.connect(database=BASE + r"\fcerta_analise.ib", user="SYSDBA", password="masterkey",
                  fb_library_name=BASE + r"\fb25\fbembed.dll", charset="NONE")
cur = con.cursor()
def dec(v): return v.decode("latin-1","replace").strip() if isinstance(v,(bytes,bytearray)) else v

# Amostra: itens de OM recentes com pesagem > 0, com dados do item, do cabeçalho e do produto
cur.execute("""
 SELECT FIRST 3000
        i.NRRQU, i.ITEMID, i.CDPRO, i.DESCR, i.QUANT, TRIM(i.UNIDA), i.QTPESA,
        i.DILUI, i.TEOR, i.EQUIV, i.VOLAPA, i.INDQSP,
        h.QTFOR, h.QTCONT, TRIM(h.VOLUME) , TRIM(h.UNIVOL),
        p.FATOR, p.TEOR, p.DILUICAO, p.DENSIDADE
 FROM FC12110 i
 JOIN FC12100 h ON h.CDFIL=i.CDFIL AND h.NRRQU=i.NRRQU AND h.SERIER=i.SERIER
 JOIN FC03000 p ON p.CDPRO=i.CDPRO
 WHERE i.QTPESA > 0 AND i.QUANT > 0 AND (i.INDQSP IS NULL OR i.INDQSP <> 'S')
 ORDER BY i.NRRQU DESC""")
rows = cur.fetchall()
print("amostra:", len(rows), "itens de OM com pesagem real (sem QSP)")

def f(x):
    try: return float(x) if x is not None else None
    except Exception: return None

modelos = Counter(); exemplos = {}
UN_MASS = {"G":1.0, "GR":1.0, "MG":0.001, "MCG":0.000001, "KG":1000.0}
for r in rows:
    (nrrqu, itemid, cdpro, descr, quant, unida, qtpesa, dilui, teor, equiv, volapa, indqsp,
     qtfor, qtcont, vol, univol, p_fator, p_teor, p_diluicao, p_dens) = r
    quant, qtpesa = f(quant), f(qtpesa)
    dilui, teor, equiv = f(dilui) or 1, f(teor) or 100, f(equiv) or 0
    qtfor = f(qtfor) or 1
    p_fator = f(p_fator) or 1
    un = (dec(unida) or "G").upper()

    if not quant or not qtpesa: continue
    base_g = quant * UN_MASS.get(un, None) if un in UN_MASS else None
    if base_g is None:  # unidades não-massa (UI, %, ML...) — tratadas depois
        modelos["unidade_nao_massa:"+un] += 1
        continue

    candidatos = {
        "dose*qtfor":                        base_g * qtfor,
        "dose*qtfor*dilui":                  base_g * qtfor * dilui,
        "dose*qtfor*dilui*100/teor":         base_g * qtfor * dilui * 100.0/teor,
        "dose*qtfor*dilui*fator":            base_g * qtfor * dilui * p_fator,
        "dose*qtfor*dilui*100/teor*fator":   base_g * qtfor * dilui * (100.0/teor) * p_fator,
        "dose*dilui":                        base_g * dilui,
        "dose":                              base_g,
    }
    achou = None
    for nome, esperado in candidatos.items():
        if esperado > 0 and abs(esperado - qtpesa)/qtpesa < 0.005:   # 0,5% tolerância
            achou = nome; break
    modelos[achou or "NAO_BATEU"] += 1
    if (achou or "NAO_BATEU") not in exemplos:
        exemplos[achou or "NAO_BATEU"] = (dec(descr)[:30], quant, un, qtfor, dilui, teor, p_fator, qtpesa)

print("\nresultado por modelo de cálculo (tolerância 0,5%):")
tot = sum(modelos.values())
for m, c in modelos.most_common(12):
    ex = exemplos.get(m)
    print(f"  {c:5d} ({c*100//tot:3d}%)  {m}")
    if ex: print(f"          ex: {ex[0]} | dose {ex[1]}{ex[2]} x{ex[3]:.0f}f dilui={ex[4]} teor={ex[5]} fator={ex[6]} => pesou {ex[7]}")
con.close()
