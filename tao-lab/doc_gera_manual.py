# -*- coding: utf-8 -*-
# Manual do Usuário do TAO Lab em DOCX — detalhado, com prints das telas E dos
# formulários de cadastro + tabelas explicando cada campo e as regras.
import os
from docx import Document
from docx.shared import Pt, Cm, RGBColor
from docx.enum.text import WD_ALIGN_PARAGRAPH

PRINTS = r"C:\Users\carlo\AppData\Local\Temp\claude\C--Users-carlo\fc46e316-08dd-4693-9e35-805a6cb6dffe\scratchpad\prints"
OUT    = r"C:\Users\carlo\Manual_Usuario_TAO_Lab_v2.docx"
AZUL   = RGBColor(0x1e, 0x40, 0xaf)
CINZA  = RGBColor(0x64, 0x74, 0x8b)

doc = Document()
for s in doc.sections:
    s.top_margin = s.bottom_margin = Cm(1.8); s.left_margin = s.right_margin = Cm(1.8)
st = doc.styles['Normal']; st.font.name = 'Calibri'; st.font.size = Pt(11)

def h1(t):
    p = doc.add_heading(t, level=1); p.runs[0].font.color.rgb = AZUL; return p
def h2(t):
    p = doc.add_heading(t, level=2); p.runs[0].font.color.rgb = AZUL; return p
def h3(t):
    p = doc.add_heading(t, level=3); p.runs[0].font.color.rgb = AZUL; return p
def para(t, italic=False):
    p = doc.add_paragraph(); r = p.add_run(t); r.italic = italic; return p
def passo(t):
    doc.add_paragraph(t, style='List Number')
def nota(t):
    p = doc.add_paragraph(); r = p.add_run('Regra: '); r.bold = True; r.font.color.rgb = AZUL; p.add_run(t)
def imagem(nome, legenda=None):
    fp = os.path.join(PRINTS, nome + '.png')
    if os.path.isfile(fp):
        doc.add_picture(fp, width=Cm(16.8))
        doc.paragraphs[-1].alignment = WD_ALIGN_PARAGRAPH.CENTER
        if legenda:
            c = doc.add_paragraph(); c.alignment = WD_ALIGN_PARAGRAPH.CENTER
            r = c.add_run('Figura — ' + legenda); r.italic = True; r.font.size = Pt(9); r.font.color.rgb = CINZA
def tabela_campos(linhas):
    # linhas: (campo, obrigatorio, descricao)
    t = doc.add_table(rows=1, cols=3); t.style = 'Light Grid Accent 1'
    hdr = t.rows[0].cells
    for i, txt in enumerate(['Campo', 'Obrig.', 'Descrição e regra']):
        hdr[i].paragraphs[0].add_run(txt).bold = True
    for campo, ob, desc in linhas:
        c = t.add_row().cells
        c[0].paragraphs[0].add_run(campo).bold = True
        c[1].text = 'Sim' if ob else '—'
        c[2].text = desc
    # larguras
    for row in t.rows:
        row.cells[0].width = Cm(3.6); row.cells[1].width = Cm(1.6); row.cells[2].width = Cm(11.6)
    doc.add_paragraph()

# ── CAPA ──
t = doc.add_paragraph(); t.alignment = WD_ALIGN_PARAGRAPH.CENTER
r = t.add_run('\n\n\nManual do Usuário'); r.bold = True; r.font.size = Pt(32); r.font.color.rgb = AZUL
s = doc.add_paragraph(); s.alignment = WD_ALIGN_PARAGRAPH.CENTER
r = s.add_run('TAO Lab — Farmácia de Manipulação'); r.font.size = Pt(18)
s2 = doc.add_paragraph(); s2.alignment = WD_ALIGN_PARAGRAPH.CENTER
r = s2.add_run('Cadastros · Orçamento · Estoque · Produção'); r.font.size = Pt(13); r.font.color.rgb = CINZA
d = doc.add_paragraph(); d.alignment = WD_ALIGN_PARAGRAPH.CENTER
r = d.add_run('\nAcesso: portal solucoesetao.com.br/robos → menu Fórmulas\nVersão de 08/07/2026'); r.font.size = Pt(11)
doc.add_page_break()

h1('Apresentação')
para('Este manual descreve, tela a tela e campo a campo, como operar o TAO Lab — o conjunto de módulos que substitui o Formula Certa na farmácia de manipulação: cadastros, orçamento, entrada de notas fiscais, controle de estoque por lote, produção com rastreabilidade, rótulo e livro de receituário.')
para('As telas funcionam no computador e no celular, pelo portal (solucoesetao.com.br/robos → menu Fórmulas). As imagens deste manual foram capturadas do próprio portal.')
h3('Convenções')
para('• Campos marcados com * (asterisco) são obrigatórios.')
para('• Onde houver "Regra:", trata-se de um comportamento automático ou validação do sistema.')
para('• O cadastro de cliente/paciente é ÚNICO em toda a solução — o mesmo contato do Agente de WhatsApp, do CRM e das Campanhas.', italic=True)

# ══════════ 1. CONFIGURAÇÕES ══════════
doc.add_page_break(); h1('1. Configurações da Farmácia')
para('Menu Fórmulas → Configurações. Ponto de partida: sem estes dados o rótulo não pode ser emitido corretamente.')
imagem('config', 'Tela de Configurações no portal')
h3('Dados da Farmácia')
tabela_campos([
 ('Razão social', True, 'Nome jurídico da farmácia. Sai no rótulo e na NF.'),
 ('Nome fantasia', False, 'Nome comercial (aparece no topo do rótulo).'),
 ('CNPJ', True, 'Somente números. Identifica a farmácia no rótulo e no fiscal.'),
 ('Inscrição Estadual / Municipal', False, 'Para emissão fiscal.'),
 ('Endereço, Bairro, Cidade, UF, CEP', True, 'Endereço completo — obrigatório no rótulo (RDC 67).'),
 ('Telefone / E-mail', False, 'Contato da farmácia.'),
 ('Farmacêutico(a) RT', True, 'Nome do Responsável Técnico. Obrigatório no rótulo.'),
 ('CRF / UF do CRF', True, 'Registro do RT no Conselho. Sai no rótulo.'),
 ('AFE, CEVS, CRF-PJ, Autorização Especial', False, 'Números das licenças sanitárias, para conformidade.'),
])
nota('Clique em "Salvar dados da farmácia". Esses dados alimentam automaticamente todo rótulo emitido.')
h3('Motor farmacotécnico v2')
para('Marque "Ativar no editor de orçamentos" para ligar: equivalência do sinônimo (sal↔base), alerta de dose máxima, trava de substância restrita/bloqueada e uso do teor real do lote no cálculo. Desligado, o cálculo permanece no modo simples. Recomenda-se manter ligado.')

# ══════════ 2. PRODUTOS / ATIVOS ══════════
doc.add_page_break(); h1('2. Produtos / Ativos')
para('Menu Fórmulas → Ativos. Cadastro de matérias-primas (MP) e embalagens.')
imagem('ativos', 'Lista de ativos')
para('Use a busca por nome ou código. Clique no nome de um produto para ver os detalhes e os sinônimos. Para cadastrar, clique em "+ Novo Ativo".')
h2('Formulário de cadastro do ativo')
imagem('form_ativo', 'Formulário Novo/Editar Ativo')
tabela_campos([
 ('Nome', True, 'Nome do produto (gravado em maiúsculas).'),
 ('Grupo', True, 'Matéria-Prima ou Embalagem.'),
 ('Código', False, 'Código interno (FC). Regra: não pode repetir — se já existir em outro produto, o sistema recusa.'),
 ('Unidade (compra)', False, 'Como o produto é comprado (G, ML, UN).'),
 ('Unidade padrão (venda)', False, 'Unidade usada no orçamento (g, mg, ml, un).'),
 ('Categoria', False, 'Classificação livre (ex.: ativo, excipiente).'),
 ('Preço compra', False, 'Valor pago ao fornecedor. Atualizado automaticamente pela entrada de NF.'),
 ('Custo/unid', False, 'Custo unitário para cálculo de margem.'),
 ('Preço venda', False, 'Preço por unidade usado no orçamento.'),
 ('Markup', False, 'Multiplicador de venda (venda = custo × markup).'),
 ('DCB', False, 'Denominação Comum Brasileira. Usada para dose máxima e identificação.'),
 ('Diluição (1:N)', False, 'Fator de diluição da MP (ex.: 20 = 1:20). Entra no cálculo da pesagem.'),
 ('Teor (%)', False, 'Concentração real do princípio ativo. O motor divide a dose pelo teor.'),
 ('Densidade (g/mL)', False, 'Para líquidos e cálculo de volume em cápsula (VOLAPA).'),
 ('Fator de perda', False, 'Acréscimo por perda de processo (ex.: 1,05 = 5%).'),
 ('Fator de correção', False, 'Correção farmacotécnica adicional (padrão 1).'),
 ('Concentração (UI/UFC/g)', False, 'Para vitaminas/probióticos em UI, UFC ou BLH.'),
 ('Restrição', False, 'Regra: "bloqueada" impede o uso no orçamento; "restrita" apenas alerta. Usado para controle (ex.: GLP-1).'),
 ('Dose máxima / Unid. dose máx', False, 'Regra: com o Motor v2 ligado, dose acima deste valor destaca o campo em vermelho no orçamento.'),
 ('Observações', False, 'Notas livres.'),
])
nota('Os campos técnicos (diluição, teor, densidade, fator de perda) alimentam o cálculo da fórmula no orçamento. A aba Sinônimos guarda os nomes alternativos que o sistema reconhece na prescrição.')

# ══════════ 3. PRESCRITORES ══════════
doc.add_page_break(); h1('3. Prescritores')
para('Menu Fórmulas → Prescritores. Médicos, dentistas, veterinários e nutricionistas.')
imagem('prescritores', 'Lista de prescritores')
h2('Formulário de cadastro do prescritor')
imagem('form_prescritor', 'Formulário Novo Prescritor')
tabela_campos([
 ('Tratamento', False, 'Dr, Dra, etc. Aparece no rótulo antes do nome.'),
 ('Nome', True, 'Nome do prescritor.'),
 ('Tipo registro', False, 'CRM, CRO, CRMV, CRN… (conselho profissional).'),
 ('Nº registro', False, 'Número no conselho.'),
 ('UF registro', False, 'Estado do registro.'),
 ('Especialidade', False, 'Especialidade médica/odontológica.'),
 ('E-mail / Celular / Telefone', False, 'Contatos do prescritor.'),
 ('Endereço / Cidade / UF / CEP', False, 'Endereço do consultório.'),
 ('Observações', False, 'Notas livres.'),
])
nota('No orçamento, o campo Prescritor busca diretamente neste cadastro (por nome ou número). Não é obrigatório informar prescritor no orçamento.')

# ══════════ 4. CLIENTE / PACIENTE ══════════
doc.add_page_break(); h1('4. Cliente / Paciente')
para('O cliente é único em toda a solução (o mesmo do CRM/Agente/Campanha). É gerenciado na tela de Histórico (Fórmulas → Histórico): busque o cliente e use "Editar dados", ou "+ Novo Cliente".')
imagem('form_cliente', 'Formulário Novo/Editar Cliente')
tabela_campos([
 ('Nome', True, 'Nome do paciente.'),
 ('Sexo', False, 'Feminino / Masculino.'),
 ('Nascimento', False, 'Data de nascimento.'),
 ('WhatsApp', True, 'Regra: é a CHAVE ÚNICA do cadastro. Se já existir um contato com esse número, o sistema reaproveita o mesmo (não duplica a pessoa).'),
 ('E-mail', False, 'E-mail do cliente.'),
 ('Características de saúde', False, 'Marcadores comuns: Obesidade, Colesterol, Pressão, Diabetes. Aparecem como selo ao abrir o cliente.'),
 ('Alergias', False, 'Texto livre — destacado no atendimento.'),
 ('Observações', False, 'Notas livres.'),
])
nota('Ao salvar, os dados valem para o CRM, o Agente e as Campanhas — é o mesmo contato. Não crie cadastros paralelos.')

# ══════════ 5. FORNECEDORES ══════════
doc.add_page_break(); h1('5. Fornecedores')
para('Menu Cotações → Fornecedores. O cadastro do fornecedor é essencial para importar as notas fiscais de compra.')
imagem('form_fornecedor', 'Formulário Novo Fornecedor')
tabela_campos([
 ('Nome do fornecedor', True, 'Nome comercial.'),
 ('WhatsApp', True, 'Número para envio de cotações (só dígitos, com DDD).'),
 ('Pessoa de contato', False, 'Nome do vendedor/atendente.'),
 ('CNPJ', False, 'Regra: é o que CASA com o XML da nota fiscal de entrada. Preencha para a importação de NF reconhecer o fornecedor automaticamente.'),
 ('Inscrição Estadual', False, 'Para conferência fiscal.'),
 ('Razão social / Nome fantasia', False, 'Dados fiscais do fornecedor.'),
 ('Telefone fixo / E-mail', False, 'Contatos para pedidos e notas.'),
 ('Endereço / Cidade / UF / CEP', False, 'Endereço do fornecedor.'),
 ('Prazo de pagamento', False, 'Ex.: 28/35/42 dias, boleto.'),
 ('Observações', False, 'Pedido mínimo, prazo de entrega, etc.'),
])
nota('Sem o CNPJ cadastrado, a entrada de NF não consegue vincular a nota ao fornecedor — cadastre o CNPJ antes de importar a primeira nota.')

# ══════════ 6. NOVO ORÇAMENTO ══════════
doc.add_page_break(); h1('6. Novo Orçamento')
para('Menu Fórmulas → Novo Orçamento. Monta a fórmula e calcula o preço.')
imagem('orc_novo', 'Editor de Orçamento')
passo('Informe o Paciente (quem usa). Se quem contrata for outra pessoa (mãe/filho), preencha também o Cliente. Prescritor e Posologia são opcionais.')
passo('Escolha a Forma Farmacêutica e o volume/quantidade; para cápsulas, o tipo de cápsula.')
passo('Clique em "Adicionar Ativo", digite o nome e escolha na lista; informe a dose e a unidade. No excipiente, marque QSP.')
passo('Botão "Fórmula padrão" aplica uma fórmula pronta. Embalagens e cápsulas são sugeridas automaticamente.')
passo('Use "Análise de Preços" para ver a margem por item e aplicar margem ao valor final. Salve.')
nota('Com o Motor v2 ligado: sinônimos com equivalência ajustam a quantidade automaticamente; doses acima do máximo ficam em vermelho; substâncias bloqueadas são recusadas.')

# ══════════ 7. HISTÓRICO E REPETIÇÃO ══════════
doc.add_page_break(); h1('7. Histórico e Repetição')
para('Menu Fórmulas → Histórico. Consulta as fórmulas passadas (base FCerta 2018–2026) e permite repetir.')
imagem('historico', 'Tela de Histórico')
passo('Digite o nome do cliente (mín. 3 letras); navegue com as setas ↑↓ e Enter.')
passo('A lista mostra data, resumo dos ativos e valor. Expanda para ver componentes e posologia.')
passo('Botão "↻ Repetir": recria o orçamento com a mesma forma, cápsula, volume e itens; só a data muda; os preços são recalculados e o valor da última aprovação vai para as observações.')

# ══════════ 8. ESTOQUE — ENTRADA DE NF ══════════
doc.add_page_break(); h1('8. Estoque — Entrada de NF')
para('Menu Fórmulas → Estoque — Entrada NF. Importa a nota fiscal de compra.')
imagem('estoque_nf', 'Tela de Entrada de NF')
passo('Clique em "Carregar" e selecione o arquivo XML da NF-e.')
passo('O sistema identifica o fornecedor pelo CNPJ; se não existir, cadastre-o em Cotações → Fornecedores com esse CNPJ e recarregue.')
passo('Na conferência, os itens já vêm associados se aquele fornecedor foi usado antes; os novos, associe ao ativo (a associação é memorizada).')
passo('Escolha o destino do valor de cada item: Compra (padrão), Custo ou Ambos.')
passo('Clique em "Efetivar": cria os lotes, lança o estoque, atualiza os preços e gera as contas a pagar.')
nota('O de-para (código do fornecedor → ativo) é aprendido uma vez; nas próximas notas do mesmo fornecedor o item já vem associado.')

# ══════════ 9. ESTOQUE — LOTES ══════════
doc.add_page_break(); h1('9. Estoque — Lotes')
para('Menu Fórmulas → Estoque — Lotes. Controle de qualidade, saldo, inventário e kardex.')
imagem('estoque_lotes', 'Tela de Lotes e Saldo')
passo('CQ de recebimento: lotes novos entram em "quarentena". Clique em "✔ Aprovar" (ou reprovar). Só lote aprovado é usado na produção (RDC 67).')
passo('Botão ⚖ (inventário): informe a quantidade real contada — gera um ajuste com registro de quem fez.')
passo('Botão ↔ (kardex): extrato de entradas e saídas do produto.')
nota('Lotes com validade a vencer (< 90 dias) aparecem em vermelho. Só lotes aprovados aparecem para uso na pesagem da produção.')

# ══════════ 10. ESTOQUE — REPOSIÇÃO ══════════
doc.add_page_break(); h1('10. Estoque — Reposição')
para('Menu Fórmulas → Estoque — Reposição. Define mínimos e gera a cotação de compra.')
imagem('form_reposicao', 'Definição de mínimo/máximo/curva de um ativo')
tabela_campos([
 ('Ativo', True, 'Busque o produto a monitorar.'),
 ('Mínimo', True, 'Saldo abaixo do qual o item entra em alerta de reposição.'),
 ('Máximo', False, 'Nível de reabastecimento; a quantidade sugerida vai até aqui.'),
 ('Curva', False, 'Classificação ABC (A = mais crítico).'),
])
passo('Os itens abaixo do mínimo aparecem destacados em vermelho, com a quantidade sugerida.')
passo('Marque os itens e clique em "🛒 Gerar cotação" — cria uma cotação no módulo Cotações com os produtos e o último preço pago.')

# ══════════ 11. PRODUÇÃO ══════════
doc.add_page_break(); h1('11. Produção — Ordem de Manipulação')
para('Menu Fórmulas → Produção. Onde a fórmula é produzida, com rastreabilidade completa.')
imagem('producao', 'Kanban de produção')
passo('Para gerar a OM: busque o orçamento (nº ou paciente) no topo e confirme. A OM é criada com validade calculada (floral 90 dias, demais 120).')
passo('As OMs aparecem no kanban por etapa (Conferência → Pesagem → … → Entregue).')
passo('Abra a OM. Na Pesagem, informe a quantidade pesada de cada componente e escolha o lote usado — só lotes aprovados; o de validade mais próxima vem sugerido.')
passo('Mova a OM pelas etapas. Ao chegar numa etapa final, ela é concluída e o estoque é baixado dos lotes pesados.')
passo('Botão "🏷 Rótulo (RDC 67)": abre o rótulo pronto para impressão.')
nota('A escolha do lote em cada componente é o que garante a rastreabilidade: lote de MP → OM → paciente. A baixa de estoque é feita uma única vez por OM.')

# ══════════ 12. LIVRO DE RECEITUÁRIO ══════════
doc.add_page_break(); h1('12. Livro de Receituário')
para('Menu Fórmulas → Livro de Receituário. Registro legal sequencial das manipulações (Lei 5.991 art. 42 + RDC 67).')
imagem('livro', 'Tela do Livro de Receituário')
passo('Escolha o período (de/até) e clique em "Gerar".')
passo('A lista traz, em ordem, o nº da OM, data, paciente, prescritor, fórmula, validade e situação.')
passo('Botão "🖨 Imprimir" gera a versão para arquivo/impressão.')

# ══════════ 13. CONTAS A PAGAR ══════════
doc.add_page_break(); h1('13. Contas a Pagar')
para('Menu Fórmulas → Contas a Pagar. As duplicatas geradas pelas notas de compra.')
imagem('contas_pagar', 'Tela de Contas a Pagar')
passo('Filtre por situação (aberto, pagas, todas) e por vencimento.')
passo('Clique em "✔ pagar" quando quitar (registra a data). "Reabrir" desfaz.')
passo('Botão "🖨 Relatório (contador)" gera a versão imprimível para a contabilidade.')
nota('Contas vencidas e ainda em aberto aparecem em vermelho.')

doc.save(OUT)
print('DOCX gerado:', OUT, '-', os.path.getsize(OUT)//1024, 'KB')
