# Screenshots comerciais — negócio demo "Farmácia Modelo"

Gera as **12 telas oficiais do TAO Neo** (1920×1080, visão do cliente/Gestor) com um
negócio demo 100% fictício, para uso em vídeo/site comercial. **Zero dados reais** (LGPD).

## Como regerar (quando o sistema evoluir)

```bash
cd _tools/demo-screenshots
python seed_demo.py       # 1) semeia/atualiza o negócio demo (idempotente)
python captura_demo.py    # 2) captura as 12 telas em ./capturas/
```

Pré-requisitos na máquina: `pip install playwright requests` + Chrome instalado;
chave Supabase em `C:\Users\carlo\FCertaSync\supabase_key.txt` (ou env `SUPABASE_KEY`);
chave SSH `C:\Users\carlo\.ssh\tao_crm_deploy` (wp-cli no Hostinger).

## O que o seed cria (âncora: `clientes.instancia_whats = 'farmacia-modelo-demo'`)

| Área | Conteúdo |
|---|---|
| Negócio | "Farmácia Modelo" (tipo farmacia) + workspace CRM, persona **Bia** com system prompt completo |
| Catálogo | 10 produtos plausíveis, 3 categorias, 4 campos extras (princípio ativo, dosagem, indicação, uso contínuo) |
| CRM | funil "Vendas" (8 estágios, template farmácia) com 9 cards fictícios; 2 cards com conversa completa (robô responde preço → confirmação → handoff humano) |
| Agente | histórico de 4 conversas na tabela `historico` |
| Campanha | "Semana da Imunidade" com `[nome]`/2 variantes e 40 destinatários (25 enviados / 12 pendentes / 3 falhas) |
| Caixa | 4 formas de pagamento, sessão aberta (saldo R$ 200) e 12 vendas de hoje (PIX/cartão/dinheiro) com recibos e taxas |
| Acesso | usuário WP **gestor.demo** (role `cbpm_gestor`, sem admin) + perfil RBAC "Gestor" (fórmulas ocultas) |

Todos os nomes são Demo/Exemplo/Modelo e os telefones são da faixa fictícia `5511 96000-00xx` / `5511 9601-xxxx`.
O seed é **idempotente** (busca por chave natural antes de inserir) e **não toca nenhum negócio existente**.
Credenciais do gestor ficam em `demo_credentials.json` (gitignorado). IDs em `demo_ids.json`.

## Decisões de captura (leia antes de estranhar)

- **01, 02 e 05 são capturadas com sessão admin** apontada para o negócio demo: o produto
  restringe essas telas a `manage_options` (histórico do agente e CRM→Configurações).
  O script **sanitiza o DOM** antes do print (esconde nomes de negócios reais e troca o
  e-mail do admin) e **restaura o negócio-ativo do admin** ao final. As demais 9 telas
  são a visão real do usuário Gestor.
- **12 usa Configurações → Caixa → Formas de Pagamento** (config de módulo, visão do
  cliente). A tela `/robos/configuracoes/` (Plataforma) NUNCA deve ir para material
  comercial: expõe URLs internas de Supabase/N8N.
- Timestamps do seed são relativos a "hoje" — rode o seed de novo no dia da captura
  para o Caixa/conversas mostrarem o dia corrente.

## Limpeza (remover a demo)

1. Rodar `cleanup_demo.sql` no SQL Editor do Supabase (idempotente; só apaga o demo).
2. No servidor (SSH): `wp user delete gestor.demo --yes` e apagar as options
   `tao_crm_gestores_ws_<WS>` e `tao_crm_pos_vendas_pipeline_<WS>` (o `<WS>` está no
   `demo_ids.json` ou no SELECT de conferência do próprio SQL).
