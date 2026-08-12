# Segurança — Supabase / RLS / LGPD

*Documento interno.* Projeto Supabase "Robôs" `gclayesytzzpzkjvgede`.

## Situação
- ✅ **Chave secreta removida do frontend** (12/08): pontos em `tao-crm.php` e `chatbot-platform.php` (`wp_localize_script`) que ainda entregavam a chave ao navegador foram zerados. Nenhum JS usa a chave (acesso é 100% server‑side).
- ⚠️ A chave exposta deve ser tratada como **comprometida** → rotacionar.
- ⚠️ **RLS desligado** nas tabelas públicas (alerta do advisor).

## Plano de remediação (executar fora do expediente)
1. **Ligar RLS** — SQL pronto: `_seguranca/rls_enable_v1.sql` (116 tabelas, transação, rollback). Backend usa chave **secret** (bypassa RLS) e N8N idem → ligar RLS sem policies **bloqueia só o acesso anônimo** (nada usa hoje). Testar portal depois.
2. **Rotacionar a chave** (no painel Supabase) → atualizar: option WP `cbpm_supabase_key`, arquivo `FCertaSync/supabase_key.txt`, credencial N8N "Supabase TAO" → testar portal + N8N → revogar a antiga.

## Boas práticas
- Chave do Supabase **nunca** no frontend (`wp_localize_script` sempre `''`).
- Chave secret só em option WP / env / credencial N8N (server‑side).
- Backup próprio diário (`tao-lab/backup_supabase.py`, Task Scheduler).

## LGPD
Dados sensíveis (pacientes: nome/CPF/prescrições = dado de saúde). Anonimização no cadastro atende ao direito ao esquecimento preservando a rastreabilidade das OMs.
