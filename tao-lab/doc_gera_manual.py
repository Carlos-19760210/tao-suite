# -*- coding: utf-8 -*-
# Gera o Manual do Usuário do TAO Lab em DOCX com os prints das telas.
import os
from docx import Document
from docx.shared import Pt, Cm, RGBColor
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.enum.table import WD_ALIGN_VERTICAL

PRINTS = r"C:\Users\carlo\AppData\Local\Temp\claude\C--Users-carlo\fc46e316-08dd-4693-9e35-805a6cb6dffe\scratchpad\prints"
OUT    = r"C:\Users\carlo\Manual_Usuario_TAO_Lab.docx"
AZUL   = RGBColor(0x1e, 0x40, 0xaf)

doc = Document()
# margens
for s in doc.sections:
    s.top_margin = s.bottom_margin = Cm(2)
    s.left_margin = s.right_margin = Cm(2)
# fonte base
st = doc.styles['Normal']; st.font.name = 'Calibri'; st.font.size = Pt(11)

def h1(t):
    p = doc.add_heading(t, level=1); p.runs[0].font.color.rgb = AZUL; return p
def h2(t):
    p = doc.add_heading(t, level=2); p.runs[0].font.color.rgb = AZUL; return p
def para(t, bold=False, italic=False, size=11):
    p = doc.add_paragraph(); r = p.add_run(t); r.bold = bold; r.italic = italic; r.font.size = Pt(size); return p
def bullet(t):
    p = doc.add_paragraph(style='List Bullet'); p.add_run(t); return p
def passo(n, t):
    p = doc.add_paragraph(style='List Number'); p.add_run(t); return p
def imagem(nome, legenda=None):
    fp = os.path.join(PRINTS, nome + '.png')
    if os.path.isfile(fp):
        doc.add_picture(fp, width=Cm(16.5))
        doc.paragraphs[-1].alignment = WD_ALIGN_PARAGRAPH.CENTER
        if legenda:
            c = doc.add_paragraph(); c.alignment = WD_ALIGN_PARAGRAPH.CENTER
            r = c.add_run('Figura — ' + legenda); r.italic = True; r.font.size = Pt(9); r.font.color.rgb = RGBColor(0x64,0x74,0x8b)

# ── CAPA ──
t = doc.add_paragraph(); t.alignment = WD_ALIGN_PARAGRAPH.CENTER
r = t.add_run('\n\n\nManual do Usuário'); r.bold = True; r.font.size = Pt(30); r.font.color.rgb = AZUL
s = doc.add_paragraph(); s.alignment = WD_ALIGN_PARAGRAPH.CENTER
r = s.add_run('TAO Lab — Farmácia de Manipulação'); r.font.size = Pt(18)
s2 = doc.add_paragraph(); s2.alignment = WD_ALIGN_PARAGRAPH.CENTER
r = s2.add_run('Módulos Fórmulas · Estoque · Produção'); r.font.size = Pt(13); r.font.color.rgb = RGBColor(0x64,0x74,0x8b)
d = doc.add_paragraph(); d.alignment = WD_ALIGN_PARAGRAPH.CENTER
r = d.add_run('\nAcesso: portal solucoesetao.com.br/robos → menu Fórmulas\nVersão de 08/07/2026'); r.font.size = Pt(11)
doc.add_page_break()

# ── INTRODUÇÃO ──
h1('Apresentação')
para('Este manual descreve, tela a tela, como operar o TAO Lab — o conjunto de módulos que substitui o sistema Formula Certa na farmácia de manipulação: do cadastro de matérias-primas ao orçamento, passando pela entrada de notas fiscais, controle de estoque por lote, produção das fórmulas com rastreabilidade e emissão de rótulo e livro de receituário.')
para('As telas funcionam tanto no computador quanto no celular, pelo portal (endereço solucoesetao.com.br/robos) ou pelo painel administrativo. As imagens deste manual foram capturadas do painel administrativo; no portal o conteúdo é o mesmo.')
para('Observação importante: o cadastro de cliente/paciente é único em toda a solução — o mesmo contato usado pelo Agente de WhatsApp, pelo CRM e pelas Campanhas. Ao editar os dados de um cliente, a alteração vale para todos os módulos.', italic=True)

SECOES = [
 ('1. Acesso e Configurações da Farmácia', 'config', 'Tela de Configurações — Dados da Farmácia, Responsável Técnico e Motor v2', [
    ('p','Antes de usar o sistema, preencha os dados da farmácia — eles são obrigatórios no rótulo (RDC 67) e na emissão fiscal.'),
    ('h2','Dados da Farmácia'),
    ('passo','Preencha razão social, nome fantasia, CNPJ, inscrições estadual e municipal, endereço completo, telefone e e-mail.'),
    ('passo','No bloco Responsável Técnico, informe o farmacêutico(a) RT, o número do CRF e a UF — esses dados saem impressos em todo rótulo.'),
    ('passo','Em Licenças sanitárias, registre AFE (ANVISA), CEVS, CRF-PJ e Autorização Especial, se houver.'),
    ('passo','Clique em "Salvar dados da farmácia".'),
    ('h2','Motor farmacotécnico v2'),
    ('p','Marque a caixa "Ativar no editor de orçamentos" para ligar os recursos avançados de cálculo: equivalência do sinônimo (sal↔base), alerta de dose máxima, trava de substância restrita/bloqueada (como GLP-1) e uso do teor real do lote. Desligado, o cálculo permanece no modo simples.'),
    ('h2','IA e integrações'),
    ('p','A chave da OpenAI (para leitura de receitas enviadas pelo card do CRM) e a chave de integração com o N8N também ficam nesta tela.'),
 ]),
 ('2. Produtos / Ativos', 'ativos', 'Tela de Ativos — lista de matérias-primas e embalagens', [
    ('p','Menu Fórmulas → Ativos. Lista todas as matérias-primas (MP) e embalagens da farmácia.'),
    ('passo','Use a busca por nome ou código para localizar um produto. Clique no nome para ver os detalhes (preços, dados técnicos, sinônimos).'),
    ('passo','Botão "+ Novo Ativo": cadastra um produto novo — nome, grupo (Matéria-Prima ou Embalagem), código, unidades, preços e os dados farmacotécnicos.'),
    ('passo','Dentro do detalhe, "Editar produto" permite alterar qualquer campo.'),
    ('p','Os campos técnicos (DCB, diluição, teor, densidade, fator de perda, dose máxima, restrição) alimentam o motor de cálculo do orçamento. A aba Sinônimos guarda os nomes alternativos que o sistema reconhece na prescrição.'),
 ]),
 ('3. Prescritores', 'prescritores', 'Tela de Prescritores', [
    ('p','Menu Fórmulas → Prescritores. Cadastro de médicos, dentistas, veterinários e nutricionistas.'),
    ('passo','Busque por nome ou número do registro. Botão "+ Novo Prescritor" abre o cadastro.'),
    ('passo','Informe o tratamento (Dr/Dra), o tipo de registro (CRM, CRO, CRMV, CRN…), o número, a UF, a especialidade, contatos e endereço.'),
    ('p','No orçamento, o campo Prescritor busca diretamente neste cadastro — não é preciso digitar tudo de novo.'),
 ]),
 ('4. Novo Orçamento', 'orc_novo', 'Editor de Orçamento', [
    ('p','Menu Fórmulas → Novo Orçamento. É onde se monta a fórmula e se calcula o preço.'),
    ('passo','Informe o Paciente (quem usa). Se quem contrata for outra pessoa (ex.: mãe/filho), preencha também o Cliente. Prescritor e Posologia são opcionais.'),
    ('passo','Escolha a Forma Farmacêutica e o volume/quantidade. Para cápsulas, o tipo de cápsula.'),
    ('passo','Clique em "Adicionar Ativo", digite o nome e escolha na lista. Informe a dose e a unidade. No excipiente, marque o botão QSP.'),
    ('passo','Botão "Fórmula padrão" aplica uma fórmula pronta do catálogo. Embalagens e cápsulas são sugeridas automaticamente.'),
    ('passo','Use "Análise de Preços" para ver a margem por item e simular/aplicar uma margem ao valor final.'),
    ('passo','Clique em Salvar. O orçamento fica disponível para aprovação e para gerar a produção.'),
    ('p','Com o Motor v2 ligado: ao buscar um ativo por um sinônimo com equivalência, o sistema ajusta a quantidade automaticamente; doses acima do máximo cadastrado ficam destacadas em vermelho; substâncias bloqueadas são recusadas.'),
 ]),
 ('5. Histórico e Repetição', 'historico', 'Tela de Histórico do Cliente', [
    ('p','Menu Fórmulas → Histórico. Consulta as fórmulas que o cliente já fez (base do Formula Certa, 2018–2026) e permite repetir.'),
    ('passo','Digite o nome do cliente (mínimo 3 letras). Navegue pela lista com as setas do teclado e Enter.'),
    ('passo','A lista mostra data, resumo dos ativos e valor de cada fórmula. Clique para expandir e ver os componentes e a posologia.'),
    ('passo','Botão "↻ Repetir": recria o orçamento no editor com a mesma forma, tipo de cápsula, volume e itens. Só a data muda; os preços são recalculados pela tabela atual e o valor da última aprovação fica registrado nas observações.'),
    ('passo','Botões "Editar dados" / "+ Novo Cliente" mantêm o cadastro do cliente (nome, WhatsApp, características de saúde, alergias) — que é o mesmo do CRM.'),
 ]),
 ('6. Estoque — Entrada de NF', 'estoque_nf', 'Tela de Entrada de Nota Fiscal', [
    ('p','Menu Fórmulas → Estoque — Entrada NF. Importa a nota fiscal de compra e dá entrada no estoque.'),
    ('passo','Clique em "Carregar" e selecione o arquivo XML da NF-e enviada pelo fornecedor.'),
    ('passo','O sistema identifica o fornecedor pelo CNPJ. Se ele não estiver cadastrado, cadastre-o em Cotações → Fornecedores com esse CNPJ e recarregue a nota.'),
    ('passo','Na conferência, cada item já vem associado se aquele fornecedor já foi usado antes. Os itens novos, associe ao ativo correspondente (busca por nome) — essa associação é lembrada e não precisa ser refeita nas próximas notas.'),
    ('passo','Em cada item, confira o "destino do valor": Compra (padrão), Custo ou Ambos — define se o preço da nota atualiza o preço de compra, o custo ou os dois.'),
    ('passo','Clique em "Efetivar entrada". O sistema cria os lotes, lança o estoque, atualiza os preços e gera as contas a pagar das duplicatas.'),
 ]),
 ('7. Estoque — Lotes (CQ, inventário, kardex)', 'estoque_lotes', 'Tela de Lotes e Saldo', [
    ('p','Menu Fórmulas → Estoque — Lotes. Controla os lotes de matéria-prima.'),
    ('passo','Controle de Qualidade de recebimento: lotes recém-entrados ficam em "quarentena". Clique em "✔ Aprovar" (ou reprovar, informando o motivo). Só lote aprovado pode ser usado na produção — exigência da RDC 67.'),
    ('passo','Botão "⚖" (inventário): informe a quantidade real contada do lote; o sistema gera automaticamente um ajuste com registro de quem fez.'),
    ('passo','Botão "↔" (kardex): mostra o extrato de entradas e saídas do produto.'),
    ('p','Lotes com validade a vencer (menos de 90 dias) aparecem em vermelho. Filtre por situação (quarentena, aprovado, etc.) ou busque por ativo/lote.'),
 ]),
 ('8. Estoque — Reposição', 'estoque_repo', 'Tela de Reposição de Estoque', [
    ('p','Menu Fórmulas → Estoque — Reposição. Aponta o que precisa comprar e gera a cotação.'),
    ('passo','Botão "+ Definir mínimo de um ativo": busque o produto e informe o estoque mínimo, o máximo e a curva (A/B/C).'),
    ('passo','Os itens com saldo abaixo do mínimo aparecem destacados em vermelho, com a quantidade sugerida de compra (até o máximo).'),
    ('passo','Marque os itens desejados e clique em "🛒 Gerar cotação" — o sistema cria uma cotação no módulo Cotações já com os produtos, a quantidade e o último preço pago, pronta para enviar aos fornecedores.'),
 ]),
 ('9. Produção — Ordem de Manipulação', 'producao', 'Tela de Produção (kanban de OMs)', [
    ('p','Menu Fórmulas → Produção. Onde a fórmula é efetivamente produzida, com rastreabilidade completa.'),
    ('passo','Para gerar uma OM: use o campo de busca no topo, encontre o orçamento (por número ou paciente) e confirme. A Ordem de Manipulação é criada com a validade calculada (floral 90 dias, demais 120).'),
    ('passo','As OMs aparecem no kanban, organizadas por etapa (Conferência, Pesagem, Manipulação, Envase, Rotulagem, CQ/Liberação, Pronto, Entregue).'),
    ('passo','Clique numa OM para abri-la. Na seção Pesagem, informe a quantidade pesada de cada componente e escolha o lote usado — o sistema oferece apenas lotes aprovados e sugere o de validade mais próxima. Este é o ponto que garante a rastreabilidade (lote → OM → paciente).'),
    ('passo','Use os botões "Mover para etapa" para avançar a OM. Ao chegar numa etapa final, a OM é concluída e o estoque é baixado automaticamente dos lotes pesados.'),
    ('passo','Botão "🏷 Rótulo (RDC 67)": abre o rótulo pronto para impressão.'),
 ]),
 ('10. Livro de Receituário', 'livro', 'Tela do Livro de Receituário', [
    ('p','Menu Fórmulas → Livro de Receituário. O registro legal sequencial de todas as manipulações (Lei 5.991 art. 42 + RDC 67).'),
    ('passo','Escolha o período (de/até) e clique em "Gerar".'),
    ('passo','A lista traz, em ordem sequencial, o número da OM, data, paciente, prescritor, fórmula, validade e situação.'),
    ('passo','Botão "🖨 Imprimir" gera a versão para impressão/arquivo.'),
 ]),
 ('11. Contas a Pagar', 'contas_pagar', 'Tela de Contas a Pagar', [
    ('p','Menu Fórmulas → Contas a Pagar. As duplicatas geradas pelas notas fiscais de compra.'),
    ('passo','Filtre por situação (em aberto, pagas, todas) e por vencimento.'),
    ('passo','Clique em "✔ pagar" quando quitar uma duplicata (registra a data de pagamento). "Reabrir" desfaz.'),
    ('passo','Botão "🖨 Relatório (contador)" gera a versão imprimível para enviar à contabilidade.'),
    ('p','Contas vencidas e ainda em aberto aparecem em vermelho.'),
 ]),
]

for titulo, print_nome, legenda, blocos in SECOES:
    doc.add_page_break()
    h1(titulo)
    for tipo, txt in blocos:
        if tipo == 'p': para(txt)
        elif tipo == 'h2': h2(txt)
        elif tipo == 'passo': passo(0, txt)
        elif tipo == 'bullet': bullet(txt)
    imagem(print_nome, legenda)

doc.save(OUT)
print('DOCX gerado:', OUT, '-', os.path.getsize(OUT)//1024, 'KB')
