# ============================================================
# equalizar.ps1
# Rotina UNICA de equalizacao Formula Certa -> TAO (Supabase).
#   1) Localiza o .ibk mais recente na pasta de drop
#   2) Restaura (gbak) num ALTERDB.ib de trabalho (guarda o anterior)
#   3) Valida o banco restaurado (conta MPs ativas)
#   4) Roda sincronizar_tao.ps1 (extrai e empurra pro Supabase)
#
# Uso:
#   .\equalizar.ps1            -> faz tudo (inclui o sync no Supabase)
#   .\equalizar.ps1 -DryRun    -> restaura e valida; NAO sincroniza
#
# Voce so precisa soltar o .ibk gerado pelo FCerta na pasta de drop.
# ============================================================

param([switch]$DryRun)

$ErrorActionPreference = 'Stop'

$DESKTOP  = [Environment]::GetFolderPath('Desktop')
$DROP_DIR = Join-Path $DESKTOP 'Soluções & TAO\BD\BD'                       # solte o .ibk aqui
$FB_DIR   = Join-Path $DESKTOP 'MagisTAO\Fcerta\Fcerta\FB\FB64\25\64'
$GBAK     = Join-Path $FB_DIR  'gbak.exe'
$ISQL     = Join-Path $FB_DIR  'isql.exe'
$DB_DIR   = Join-Path $DESKTOP 'MagisTAO\Fcerta\Fcerta\DB'
$DB       = Join-Path $DB_DIR  'ALTERDB.ib'
$TMP_IB   = Join-Path $DB_DIR  '_restore_tmp.ib'
$SYNC     = Join-Path $PSScriptRoot 'sincronizar_tao.ps1'
$LOG      = Join-Path $DROP_DIR ('equalizar_{0}.log' -f (Get-Date -Format 'yyyyMMdd_HHmmss'))
$FBUSER   = 'SYSDBA'
$FBPWD    = 'masterkey'

function Log($m) { $t = (Get-Date -Format 'HH:mm:ss'); ("{0}  {1}" -f $t, $m) | Tee-Object -FilePath $LOG -Append }

Log "=== Equalizacao Formula Certa -> TAO ==="

# --- Checagens de ambiente ---
foreach ($f in @($GBAK, $ISQL)) {
    if (-not (Test-Path $f)) { Log "ERRO: ferramenta nao encontrada: $f"; exit 1 }
}
if (-not (Test-Path $DROP_DIR)) { Log "ERRO: pasta de drop inexistente: $DROP_DIR"; exit 1 }

# --- 1) .ibk mais recente ---
$ibk = Get-ChildItem -Path $DROP_DIR -Recurse -Filter 'ALTERDB*.ibk' -ErrorAction SilentlyContinue |
       Sort-Object LastWriteTime -Descending | Select-Object -First 1
if (-not $ibk) { Log "ERRO: nenhum ALTERDB*.ibk encontrado em $DROP_DIR"; exit 1 }
Log ("Backup mais recente: {0}  ({1:dd/MM/yyyy HH:mm} - {2:N0} MB)" -f $ibk.Name, $ibk.LastWriteTime, ($ibk.Length / 1MB))

# --- 2) Restaura para arquivo temporario (preserva o banco atual em caso de falha) ---
if (Test-Path $TMP_IB) { Remove-Item $TMP_IB -Force }
Log "Restaurando via gbak (pode levar alguns minutos)..."
& $GBAK -c -user $FBUSER -password $FBPWD "$($ibk.FullName)" "$TMP_IB" 2>&1 | Tee-Object -FilePath $LOG -Append
if ($LASTEXITCODE -ne 0 -or -not (Test-Path $TMP_IB)) {
    Log "ERRO: restore falhou (exit=$LASTEXITCODE). Banco atual NAO foi alterado."
    if (Test-Path $TMP_IB) { Remove-Item $TMP_IB -Force }
    exit 1
}

# --- 3) Valida o banco restaurado ---
$sqlVal = Join-Path $env:TEMP 'eq_val.sql'
"SELECT COUNT(*) FROM FC03000 WHERE SITUA='A' AND GRUPO='M'; QUIT;" | Set-Content -Path $sqlVal -Encoding ASCII
$valRaw = & $ISQL -user $FBUSER -password $FBPWD -input $sqlVal "$TMP_IB" 2>&1
$cnt = ($valRaw | Where-Object { $_ -match '^\s*\d+\s*$' } | Select-Object -First 1)
if (-not $cnt) {
    Log "ERRO: validacao nao retornou contagem. Saida do isql:"
    $valRaw | ForEach-Object { Log "    $_" }
    Remove-Item $TMP_IB -Force
    exit 1
}
Log ("Validacao OK: {0} materias-primas ativas (GRUPO=M) no banco restaurado." -f ($cnt.ToString().Trim()))

# --- 4) Promove o banco restaurado (com backup do anterior + retencao de 3) ---
if (Test-Path $DB) {
    $bak = Join-Path $DB_DIR ('ALTERDB_pre_{0}.ib' -f (Get-Date -Format 'yyyyMMdd_HHmmss'))
    Move-Item $DB $bak -Force
    Log "Banco anterior preservado: $(Split-Path $bak -Leaf)"
    Get-ChildItem $DB_DIR -Filter 'ALTERDB_pre_*.ib' | Sort-Object LastWriteTime -Descending |
        Select-Object -Skip 3 | Remove-Item -Force -ErrorAction SilentlyContinue
}
Move-Item $TMP_IB $DB -Force
Log "Banco restaurado promovido para ALTERDB.ib"

# --- 5) Sincroniza (a menos que -DryRun) ---
if ($DryRun) {
    Log "DryRun ativo: banco pronto, Supabase NAO foi tocado."
    Log "Concluido. Log: $LOG"
    exit 0
}
if (-not (Test-Path $SYNC)) { Log "ERRO: sincronizar_tao.ps1 nao encontrado em $PSScriptRoot"; exit 1 }
Log "Rodando sincronizar_tao.ps1 (equalizacao no Supabase)..."
& $SYNC 2>&1 | Tee-Object -FilePath $LOG -Append
Log "Concluido. Log: $LOG"

