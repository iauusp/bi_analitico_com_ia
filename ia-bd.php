<?php

/**
 * ============================================================================
 * MIT License
 * ============================================================================
 *
 * Copyright (c) 2024–2026 Seção de Informática — IAU / USP
 * Contact: iau.sti@sc.usp.br
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 * ============================================================================
 * THIRD-PARTY COMPONENTS
 * ============================================================================
 * This system uses third-party libraries and services (Apache ECharts,
 * echarts-wordcloud, Font Awesome, html2pdf.js, Google Gemini API, PHP,
 * MySQL/MariaDB, SQLite, etc.), each subject to their respective licenses
 * and terms of use. The user is responsible for complying with those terms.
 * Use of the Google Gemini API may involve costs and quota limits associated
 * with the configured API key.
 *
 * Use of this software is at the user's own risk. It is recommended to test
 * and validate all features before production use or decision-making support.
 * ============================================================================
 *
 * ============================================================================
 * SISTEMA: BI ANALÍTICO CONVERSACIONAL COM IA (GEMINI + ECHARTS)
 * VERSÃO: 45.1 - EDIÇÃO DEFINITIVA
 * COMPATIBILIDADE: PHP 5.6 ATÉ PHP 8.3+ (ZERO ERROS DE SINTAXE)
 * ============================================================================
 * Destaques da Arquitetura:
 * - Text-to-SQL analítico em tempo real com Google Gemini.
 * - Segurança estrita Read-Only (dupla camada: Engine MySQL + Regex PHP).
 * - Motor de CSV híbrido (PDO SQLite > SQLite3 nativo > PHP Puro sem dependências).
 * - Descoberta automática de tabelas (Auto-Schema) e geração dinâmica de perguntas.
 * - Memória conversacional com auto-recuperação em caso de zero resultados.
 * - Modo Apresentação Executiva em PDF (1 Pergunta + 1 Resposta = 1 Slide por página).
 * - Gestão de chave de API protegida (sem exposição no cliente) e seleção de modelos.
 */

// Suprime advertência de $HTTP_RAW_POST_DATA no PHP 5.6
@ini_set('always_populate_raw_post_data', -1);

// Constante global de segurança para evitar quebras de blocos markdown
if (!defined('TICK3')) {
    define('TICK3', "\x60\x60\x60");
}

// Inicialização segura de sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Desativa reporte estrito do mysqli no PHP 8.1+ preservando compatibilidade
if (function_exists('mysqli_report')) {
    mysqli_report(MYSQLI_REPORT_OFF);
}






/* ============================================================================
 * [ZONA DE CONTROLE] HABILITAR OU DESABILITAR RECURSOS DO SISTEMA
 * ============================================================================
 */
$enable_auth       = 1;   // 1 = Ativa tela de login obrigatória; 0 = Acesso livre direto
$auth_user         = "admin";
// Senha em MD5 (Padrão abaixo: "admin123" -> 0192023a7bbd73250516f069df18b500)
$auth_pass_md5     = "0192023a7bbd73250516f069df18b500";



/* ============================================================================
 * [ZONA DE CONTROLE] HABILITAR OU DESABILITAR RECURSOS DO SISTEMA
 * ============================================================================
 */
$enable_default_db = 1;   // 1 = Usa a base padrão abaixo; 0 = Sem base padrão (apenas Custom ou CSV)
$enable_custom_db  = 1;   // 1 = Permite conectar outra base MySQL via modal; 0 = Desabilita base customizada
$enable_csv_upload = 1;   // 1 = Permite upload e análise de planilhas CSV; 0 = Desabilita módulo CSV
$enable_help_panel = 1;   // 1 = Exibe menu lateral de atalhos e perguntas de ajuda; 0 = Oculta atalhos

// CONFIGURAÇÃO DA ORIENTAÇÃO DO RELATÓRIO PDF (SLIDE DECK):
// "paisagem" (landscape - recomendada para formato apresentação) ou "retrato" (portrait)
$pdf_orientation   = "retrato";


/* ============================================================================
 * [ZONA 1] CONFIGURAÇÃO DA BASE DE DADOS PADRÃO (MySQL / MariaDB)
 * ============================================================================
 */
$db_config = array(
    'host' => 'localhost',
    'port' => 3306,
    'user' => '',
    'pass' => '',
    'name' => ''
);

/* ============================================================================
 * [ZONA 2] DIRETÓRIO BASE PARA ARQUIVOS EXTERNOS / ANEXOS
 * ============================================================================
 */
$base_file_url = "";

/* ============================================================================
 * [ZONA 3] CREDENCIAIS E MODELOS DA API GOOGLE GEMINI
 * ============================================================================
 * A chave abaixo NUNCA será enviada ao navegador ou exibida na interface.
 */
$gemini_api_key = "SUA_CHAVE_GEMINI_AQUI";   // <<<<<<< IMPORTANTE INSERIR SUA CHAVE GEMINI

// Modelo que será sempre o padrão inicial
$gemini_model = "gemini-3.6-flash";

// Array de modelos permitidos para seleção
$gemini_available_models = array(
    "gemini-3.6-flash",
    "gemini-2.5-flash",
    "gemini-2.0-flash",
    "gemini-1.5-flash"
);

/* ============================================================================
 * [ZONA 4] CONTROLE DO STRUCTURED RAG (ROUTER / SCHEMA PRUNING)
 * ============================================================================
 */
$enable_structured_rag = 0;

/* ============================================================================
 * [ZONA 5] ESTRUTURA DO BANCO DE DADOS PADRÃO (DDL - SQL)
 * ABAIXO EXEMPLO DE UMA TABELA, FORMATAR PARA CONTEMPLAR TODA A ESTRUTURA DDL DE SEU BD
 * AO DEIXAR VAZIO O SISTEMA EXTRAI A ESTRUTURA DA CONEXÃO COM O BD CONFIGURADO
 * ============================================================================
 */
$database_schema = "
Tabela `relatorios`:
- Colunas:
  `id` int(11) NOT NULL (Chave primária, identificador único do relatório de afastamento),
  `nro_usp` varchar(20) (Número USP do docente, identificador único da pessoa),
  `nome` varchar(255) (Nome Completo do docente),
  `modalidade` varchar(100) (Valores comuns: 'Sem prejuízo dos vencimentos', 'Com prejuízo dos vencimentos'),
  `local` varchar(255) (Cidade, Estado do afastamento),
  `pais` varchar(255) (país de destino do afastamento, quando falar em países ou pais de destino usar este campo),
  `instituicao` varchar(255) (Instituição visitada ou onde ocorreu o evento),
  `data_inicio` date (Data de início do afastamento),
  `data_fim` date (Data de término do afastamento),
  `fonte_recursos` text (Origem do financiamento da viagem),
  `finalidade` text (É o Motivo principal do afastamento, ex: participação em congresso, pesquisa de campo),
  `atividades` text (Descrição das atividades realizadas durante o afastamento),
  `resultados` text (Resultados obtidos com o afastamento),
  `email` varchar(255) (E-mail do docente),
  `pdf_path`  ( nome do arquivo com relatório PDF gerado, IMPORTANTE para criar o link para o arquivo ),
  `created_at` timestamp (Data de criação do registro)
- Descrição: A tabela armazena relatórios detalhados sobre afastamentos e viagens de docentes. Cada linha é um afastamento.
- Campos irrelevantes para a maioria das consultas: pdf_path.
";

/* ============================================================================
 * [ZONA 6] EXEMPLOS DE ATALHOS / PERGUNTAS FREQUENTES (BASE PADRÃO)
 * ABAIXO EXEMPLO DE PERGUNTAS, FORMATAR PARA PERGUNTAS PADRÃO, RELATIVAS AO SEU BD
 * ============================================================================
 */
$help_examples = array(
    "Gere um dashboard geral de afastamentos com total de afastamentos, Top 10 Países de Destino dos Afastamentos, Gráfico pizza com todos os países de destino, Afastamentos por Ano, um Gráfico de linha com a evolução do total dos afastamentos mês a mês e um heatmap de mês e ano",
    "Listar todos os relatórios",
    "Listar ultimos 10 relatórios",
    "Lista de todos os relatórios sem os que tem destino país Brasil",
    "Afastamentos do docente: ",
    "Gráfico de barras: total por país",
    "Nuvem de palavras com as atividades",
    "Nuvem de palavras das atividades no exterior",
    "Gráfico de barras com afastamentos para exterior entre os anos:  ",
    "Gráfico de linha com a evolução do total dos afastamentos mês a mês",
    "Timeline por data e destino, afastamentos docente: ",
    "Mapa de calor: Afastamentos por Mês e Ano",
    "heatmap Afastamentos por Mês e Ano",
    "Heatmap Docente e País",
    "Gere um dashboard geral de afastamentos",
    "Resumo com total por meses e países do ano de: ",
    "Heatmap Mês x Ano",
    "Heatmap: ",
    "Grafo de relações entre os docentes e destino dos afastamentos",
    "Grafo de relações entre os nomes dos docentes e destino dos afastamentos excluindo Brasil",
    "Gráfico de pizza: total por país de destino",
    "Gráfico de pizza: total de afastamentos por docente",
    "Incluir na tabela de resultados data de inicio e data fim, na ficha detalhada todos os campos, inclusive o link para o arquivo de relatório, do docente: ",
    "Número total de dias em afastamento do docente: ",
    "Mapa de calor: Afastamentos por Mês e Ano",
    "Texto com análise dos resultados dos afastamentos do Docente: ",
    "Dashboard: Países, Modalidades e Evolução Mensal",
    "Exportar lista de afastamentos"
);





// Normalização da orientação padrão do PDF
$raw_orient = strtolower(trim((string)$pdf_orientation));
$normalized_pdf_orientation = ($raw_orient === 'portrait' || $raw_orient === 'retrato') ? 'portrait' : 'landscape';

// --- LOGOUT DO SISTEMA ---
if (isset($_GET['logout'])) {
    unset($_SESSION['bi_authenticated']);
    unset($_SESSION['chat_history']);
    unset($_SESSION['query_cache']);
    header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
    exit();
}

// --- CONTROLE DE ACESSO / LOGIN ---
if ($enable_auth && empty($_SESSION['bi_authenticated'])) {
    $login_error = "";
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_user'])) {
        $u_input = trim((string)$_POST['login_user']);
        $p_input = trim((string)$_POST['login_pass']);
        if ($u_input === $auth_user && md5($p_input) === strtolower($auth_pass_md5)) {
            $_SESSION['bi_authenticated'] = true;
            header("Location: " . $_SERVER['REQUEST_URI']);
            exit();
        } else {
            $login_error = "Credenciais inválidas. Tente novamente.";
        }
    }

    // Se for requisição AJAX sem login, retorna erro JSON
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['login_user'])) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array('status' => 'error', 'message' => 'Sessão expirada. Faça login novamente.'));
        exit();
    }
?>
    <!DOCTYPE html>
    <html lang="pt-br">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Login • IA Análises Banco de Dados</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        <style>
            :root {
                --primary: #1e40af;
                --secondary: #3b82f6;
                --bg: #0f172a;
            }

            body {
                margin: 0;
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                background: var(--bg);
                height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                color: #1e293b;
            }

            .login-card {
                background: white;
                padding: 40px;
                border-radius: 20px;
                box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
                width: 100%;
                max-width: 380px;
                box-sizing: border-box;
            }

            .login-header {
                text-align: center;
                margin-bottom: 28px;
            }

            .login-icon {
                font-size: 42px;
                color: var(--primary);
                margin-bottom: 12px;
            }

            .login-title {
                font-size: 20px;
                font-weight: 800;
                color: #0f172a;
                margin: 0;
            }

            .login-sub {
                font-size: 12px;
                color: #64748b;
                margin-top: 5px;
            }

            .form-group {
                margin-bottom: 16px;
            }

            .form-group label {
                display: block;
                font-size: 11px;
                font-weight: 700;
                color: #475569;
                margin-bottom: 6px;
                text-transform: uppercase;
            }

            .form-control {
                width: 100%;
                padding: 12px;
                border: 1.5px solid #cbd5e1;
                border-radius: 10px;
                font-size: 13.5px;
                box-sizing: border-box;
                outline: none;
                transition: 0.2s;
            }

            .form-control:focus {
                border-color: var(--secondary);
                box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
            }

            .btn-login {
                width: 100%;
                padding: 13px;
                background: var(--primary);
                color: white;
                border: none;
                border-radius: 10px;
                font-weight: 800;
                font-size: 13px;
                cursor: pointer;
                transition: 0.2s;
                margin-top: 10px;
            }

            .btn-login:hover {
                background: #1d4ed8;
            }

            .error-box {
                background: #fee2e2;
                color: #dc2626;
                padding: 10px;
                border-radius: 8px;
                font-size: 12px;
                font-weight: bold;
                margin-bottom: 16px;
                text-align: center;
            }
        </style>
    </head>

    <body>
        <div class="login-card">
            <div class="login-header">
                <i class="fas fa-brain login-icon"></i>
                <h1 class="login-title">IA Análises Banco de Dados</h1>
                <div class="login-sub">Acesso restrito ao Painel Analítico</div>
            </div>
            <?php if (!empty($login_error)): ?>
                <div class="error-box"><?php echo htmlspecialchars($login_error); ?></div>
            <?php endif; ?>
            <form method="POST">
                <div class="form-group">
                    <label>Usuário:</label>
                    <input type="text" name="login_user" class="form-control" required autofocus placeholder="Digite o usuário">
                </div>
                <div class="form-group">
                    <label>Senha:</label>
                    <input type="password" name="login_pass" class="form-control" required placeholder="Digite a senha">
                </div>
                <button type="submit" class="btn-login"><i class="fas fa-sign-in-alt"></i> ENTRAR NO SISTEMA</button>
            </form>
        </div>
    </body>

    </html>
<?php
    exit();
}

// --- RESOLUÇÃO DINÂMICA DA CHAVE E DO MODELO GEMINI ---
$active_gemini_key = $gemini_api_key;
$active_gemini_model = $gemini_model;
$ai_key_source = 'default';

if (isset($_SESSION['ai_config'])) {
    if (isset($_SESSION['ai_config']['key_type']) && $_SESSION['ai_config']['key_type'] === 'custom' && !empty($_SESSION['ai_config']['custom_key'])) {
        $active_gemini_key = $_SESSION['ai_config']['custom_key'];
        $ai_key_source = 'custom';
    }
    if (isset($_SESSION['ai_config']['model']) && in_array($_SESSION['ai_config']['model'], $gemini_available_models)) {
        $active_gemini_model = $_SESSION['ai_config']['model'];
    }
}

// --- FUNÇÃO: SEGURANÇA SQL LEITURA RESTRITA (READ-ONLY) ---
if (!function_exists('is_sql_safe')) {
    function is_sql_safe($sql)
    {
        $sql_str = trim((string)$sql);
        if (empty($sql_str)) return false;

        $sql_trimmed = rtrim($sql_str, "; \t\n\r\0\x0B");

        if (strpos($sql_trimmed, ';') !== false) {
            return false;
        }

        $forbidden = array(
            'INSERT',
            'UPDATE',
            'DELETE',
            'DROP',
            'ALTER',
            'TRUNCATE',
            'CREATE',
            'GRANT',
            'REVOKE',
            'COMMIT',
            'ROLLBACK',
            'REPLACE',
            'RENAME',
            'CALL',
            'EXEC',
            'EXECUTE',
            'SET',
            'LOCK',
            'UNLOCK',
            'LOAD',
            'OUTFILE',
            'INFILE',
            'ATTACH',
            'DETACH'
        );
        $sql_upper = strtoupper($sql_trimmed);

        if (strpos($sql_upper, 'SELECT') !== 0 && strpos($sql_upper, 'WITH') !== 0) {
            return false;
        }

        foreach ($forbidden as $word) {
            if (preg_match("/\b" . $word . "\b/i", $sql_upper)) {
                return false;
            }
        }
        return true;
    }
}

// --- FUNÇÃO: CONEXÃO MYSQL SEGURA (FORÇA READ-ONLY NA ENGINE) ---
if (!function_exists('create_safe_mysql_connection')) {
    function create_safe_mysql_connection($cfg)
    {
        $port = (isset($cfg['port']) && !empty($cfg['port'])) ? (int)$cfg['port'] : 3306;
        $conn = new mysqli($cfg['host'], $cfg['user'], $cfg['pass'], $cfg['name'], $port);
        if ($conn->connect_error) {
            throw new Exception("Falha de conexão com o banco de dados: " . $conn->connect_error);
        }
        $conn->set_charset("utf8mb4");
        @$conn->query("SET SESSION TRANSACTION READ ONLY");
        return $conn;
    }
}

// --- FUNÇÃO: AUTO-EXTRAÇÃO INTELIGENTE DE SCHEMA ---
if (!function_exists('extract_mysql_schema_auto')) {
    function extract_mysql_schema_auto($conn)
    {
        $schema_ddl = "";
        $tables_count = 0;

        $res = $conn->query("SHOW FULL TABLES");
        if ($res && $res instanceof mysqli_result) {
            while ($row = $res->fetch_array()) {
                $tableName = $row[0];
                $escapedTable = str_replace("`", "``", $tableName);

                $resCreate = $conn->query("SHOW CREATE TABLE `{$escapedTable}`");
                if ($resCreate && ($rowCreate = $resCreate->fetch_array())) {
                    $createSql = $rowCreate[1];
                    $createSql = preg_replace('/AUTO_INCREMENT=\d+\s*/i', '', $createSql);
                    $schema_ddl .= $createSql . ";\n\n";
                    $tables_count++;
                }
                if ($resCreate instanceof mysqli_result) $resCreate->free();
            }
            $res->free();
        }

        if ($tables_count === 0) {
            throw new Exception("Nenhuma tabela encontrada no banco de dados selecionado.");
        }

        return array(
            'schema' => trim($schema_ddl),
            'tables_count' => $tables_count
        );
    }
}

// --- RESET DE SESSÃO / HISTÓRICO / CACHE ---
if (isset($_GET['reset'])) {
    if (isset($_SESSION['chat_history'])) unset($_SESSION['chat_history']);
    if (isset($_SESSION['query_cache'])) unset($_SESSION['query_cache']);
    header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
    exit();
}

// --- FUNÇÃO: COMUNICAÇÃO HTTP COM A API GEMINI ---
if (!function_exists('call_gemini_api')) {
    function call_gemini_api($payload, $api_key, $model)
    {
        if (empty($api_key)) {
            throw new Exception("Chave da API do Google Gemini não configurada.");
        }
        $url = "https://generativelanguage.googleapis.com/v1beta/models/" . $model . ":generateContent";
        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'x-goog-api-key: ' . $api_key
            ),
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ));
        $response = curl_exec($ch);
        curl_close($ch);
        $result = json_decode((string)$response, true);
        return is_array($result) ? $result : array();
    }
}

// --- FUNÇÃO: GERADOR INTELIGENTE DE PERGUNTAS ANALÍTICAS (IA) ---
if (!function_exists('generate_ai_suggested_questions')) {
    function generate_ai_suggested_questions($schema_text, $api_key, $model, $sample_data)
    {
        if (empty($api_key)) {
            return array(
                "Dashboard geral com os principais indicadores e distribuição dos registros",
                "Listar os 10 primeiros registros",
                "Total geral de registros",
                "Gráfico de barras para: ",
                "Gráfico de pizza para: ",
                "Texto com a análise dos registros que: "
            );
        }

        $prompt = "Você é um analista sênior de BI. Analise a estrutura de dados a seguir:\n\n";
        $p_context = substr($schema_text, 0, 3500);
        if (!empty($sample_data)) {
            $p_context .= "\nAmostra de dados reais:\n" . substr($sample_data, 0, 1500);
        }
        $prompt .= $p_context . "\n\n";
        $prompt .= "SUA TAREFA:
Crie entre 6 e 10 perguntas analíticas inteligentes e realistas que um gestor faria sobre esses dados.

REGRA CRÍTICA PARA A 1ª PERGUNTA (ÍNDICE 0):
A primeira pergunta (índice 0) DEVE ser OBRIGATORIAMENTE um pedido de Dashboard Executivo completo com visão geral dos dados, indicadores principais e distribuição gráfica relevante (Exemplo: 'Dashboard geral com principais indicadores e distribuição' ou 'Painel com visão consolidada dos dados').

As demais perguntas DEVEM cobrir os formatos analíticos disponíveis:
- Totais ou médias quantitativas (KPI) (ex: 'Total de ...', 'Média de ...')
- Gráfico de barras (ex: 'Gráfico de barras para: ...')
- Gráfico de pizza / distribuição (ex: 'Gráfico de pizza para: ...')
- Gráfico de linha / histórico (se houver datas) (ex: 'Gráfico de linha para: ...')
- Linha do tempo (se houver períodos/datas) (ex: 'Fazer linha do tempo para: ...')
- Nuvem de palavras (se houver textos) (ex: 'Fazer nuvem de palavras com: ...')
- Listagem detalhada (ex: 'Listar últimos 10 ...')
- Análise gerencial (ex: 'Texto com a análise dos registros que: ...')

REGRAS:
- Retorne EXCLUSIVAMENTE um array JSON puro de strings.
- Exemplo: [\"Dashboard geral com visão consolidada\", \"Listar últimos 10 registros\", \"Total geral\"]
- Não inclua blocos markdown nem explicações fora do JSON.";

        $payload = array(
            "contents" => array(array("parts" => array(array("text" => $prompt)))),
            "generationConfig" => array("temperature" => 0.2, "response_mime_type" => "application/json")
        );

        try {
            $res = call_gemini_api($payload, $api_key, $model);
            $raw_text = isset($res['candidates'][0]['content']['parts'][0]['text']) ? $res['candidates'][0]['content']['parts'][0]['text'] : '';
            $raw_text = preg_replace('/^' . TICK3 . '(?:json)?\s*|\s*' . TICK3 . '$/i', '', trim((string)$raw_text));
            $decoded = json_decode($raw_text, true);
            if (is_array($decoded) && !empty($decoded)) {
                $clean = array();
                foreach ($decoded as $item) {
                    if (is_string($item) && strlen(trim($item)) > 3) {
                        $clean[] = trim($item);
                    }
                }
                if (!empty($clean)) return array_slice($clean, 0, 10);
            }
        } catch (Exception $e) {
        }

        return array(
            "Dashboard geral com os principais indicadores e distribuição dos registros",
            "Listar primeiros 10 registros",
            "Total de registros",
            "Gráfico de barras para: ",
            "Gráfico de pizza para: ",
            "Texto com a análise dos registros que: "
        );
    }
}

// --- FUNÇÃO: CONVERSOR UNIVERSAL DE VALORES NUMÉRICOS (MOEDA/BR/US) ---
if (!function_exists('clean_numeric_value')) {
    function clean_numeric_value($val)
    {
        if (is_numeric($val)) return (float)$val;
        $str = trim((string)$val);
        if ($str === '') return 0.0;

        $str = preg_replace('/[^\d.,-]/', '', $str);
        if ($str === '' || $str === '-' || $str === '.') return 0.0;

        if (strpos($str, '.') !== false && strpos($str, ',') !== false) {
            if (strrpos($str, ',') > strrpos($str, '.')) {
                $str = str_replace('.', '', $str);
                $str = str_replace(',', '.', $str);
            } else {
                $str = str_replace(',', '', $str);
            }
        } elseif (strpos($str, ',') !== false) {
            $str = str_replace(',', '.', $str);
        }

        return is_numeric($str) ? (float)$str : 0.0;
    }
}

// --- FUNÇÃO: RESOLVEDOR INTELIGENTE DE COLUNA EM LINHAS CSV ---
if (!function_exists('get_row_field_val_fuzzy')) {
    function get_row_field_val_fuzzy($row, $target_col)
    {
        if (isset($row[$target_col])) return $row[$target_col];

        $clean_target = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', (string)$target_col));
        if ($clean_target === '') return '';

        foreach ($row as $k => $v) {
            $clean_k = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', (string)$k));
            if ($clean_k === $clean_target) return $v;
            if (strlen($clean_k) > strlen($clean_target) && substr($clean_k, -strlen($clean_target)) === $clean_target) {
                return $v;
            }
        }
        return '';
    }
}

// --- FUNÇÃO: PARSER DE EXPRESSÕES SELECT AVANÇADO (MOTOR NATIVO PHP) ---
if (!function_exists('parse_select_exprs_native')) {
    function parse_select_exprs_native($select_str)
    {
        $parts = preg_split('/,(?![^(]*\))/', $select_str);
        $exprs = array();

        foreach ($parts as $p) {
            $p = trim($p);
            if (empty($p)) continue;

            $alias = $p;
            $clean_expr = $p;
            if (preg_match('/^(.*?)\s+(?:AS\s+)?([a-zA-Z0-9_`\'"]+)$/i', $p, $m)) {
                $clean_expr = trim($m[1]);
                $alias = trim($m[2]);
            }
            $alias = str_replace(array('`', '"', "'"), '', $alias);

            if (preg_match('/^ROUND\s*\(\s*(.*?)\s*,\s*\d+\s*\)$/i', $clean_expr, $rm)) {
                $clean_expr = trim($rm[1]);
            }

            if (preg_match('/^COUNT\s*\(\s*(.*?)\s*\)$/i', $clean_expr)) {
                $exprs[] = array('type' => 'count_all', 'col' => '*', 'alias' => $alias);
            } elseif (preg_match('/^SUM\s*\(\s*(?:CAST\s*\(\s*)?`?([a-zA-Z0-9_]+)`?(?:\s+AS\s+[a-zA-Z0-9_]+)?\s*\)?\s*\)$/i', $clean_expr, $sm)) {
                $exprs[] = array('type' => 'sum', 'col' => $sm[1], 'alias' => $alias);
            } elseif (preg_match('/^AVG\s*\(\s*(?:CAST\s*\(\s*)?`?([a-zA-Z0-9_]+)`?(?:\s+AS\s+[a-zA-Z0-9_]+)?\s*\)?\s*\)$/i', $clean_expr, $am)) {
                $exprs[] = array('type' => 'avg', 'col' => $am[1], 'alias' => $alias);
            } elseif (preg_match('/^MAX\s*\(\s*`?([a-zA-Z0-9_]+)`?\s*\)$/i', $clean_expr, $mm)) {
                $exprs[] = array('type' => 'max', 'col' => $mm[1], 'alias' => $alias);
            } elseif (preg_match('/^MIN\s*\(\s*`?([a-zA-Z0-9_]+)`?\s*\)$/i', $clean_expr, $nm)) {
                $exprs[] = array('type' => 'min', 'col' => $nm[1], 'alias' => $alias);
            } else {
                $cname = str_replace(array('`', '"', "'", 'dados_csv.'), '', $clean_expr);
                $exprs[] = array('type' => 'col', 'col' => $cname, 'alias' => $alias);
            }
        }
        return $exprs;
    }
}

// --- FUNÇÃO: AVALIADOR WHERE AVANÇADO (MOTOR NATIVO PHP) ---
if (!function_exists('eval_where_clause_native')) {
    function eval_where_clause_native($where_str, $row)
    {
        if (empty($where_str)) return true;
        $conditions = preg_split('/\s+AND\s+/i', $where_str);

        foreach ($conditions as $cond) {
            $cond = trim($cond);
            if (empty($cond)) continue;

            if (preg_match('/(?:LOWER\()?\s*`?([a-zA-Z0-9_]+)`?\s*\)?\s+LIKE\s+[\'"]%?(.*?)%?[\'"]/i', $cond, $lm)) {
                $col = $lm[1];
                $needle = mb_strtolower($lm[2], 'UTF-8');
                $val = mb_strtolower((string)get_row_field_val_fuzzy($row, $col), 'UTF-8');
                if (mb_strpos($val, $needle) === false) return false;
            } elseif (preg_match('/`?([a-zA-Z0-9_]+)`?\s*(=|!=|<>|>=|<=|>|<)\s*[\'"]?(.*?)[\'"]?$/i', $cond, $om)) {
                $col = $om[1];
                $op = $om[2];
                $target_raw = trim($om[3], "'\" \t\n\r");
                $val_raw = get_row_field_val_fuzzy($row, $col);

                if (is_numeric($val_raw) && is_numeric($target_raw)) {
                    $val = (float)$val_raw;
                    $target = (float)$target_raw;
                } else {
                    $val = (string)$val_raw;
                    $target = (string)$target_raw;
                }

                if ($op === '=' && $val != $target) return false;
                if (($op === '!=' || $op === '<>') && $val == $target) return false;
                if ($op === '>' && !($val > $target)) return false;
                if ($op === '<' && !($val < $target)) return false;
                if ($op === '>=' && !($val >= $target)) return false;
                if ($op === '<=' && !($val <= $target)) return false;
            }
        }
        return true;
    }
}

// --- FUNÇÃO: MOTOR QUANTITATIVO ANALÍTICO SQL NATIVO EM PHP ---
if (!function_exists('execute_native_php_query')) {
    function execute_native_php_query($sql, $rows)
    {
        if (empty($rows)) return array();

        $sql_norm = preg_replace('/\s+/', ' ', trim($sql));
        $sql_norm = preg_replace('/`?dados_csv`?\./i', '', $sql_norm);

        $select_part = '*';
        $where_part = '';
        $groupby_part = '';
        $orderby_part = '';
        $limit_part = 0;

        if (preg_match('/SELECT\s+(.*?)\s+FROM\s+[`\'"]?dados_csv[`\'"]?(?:\s+(?:AS\s+)?[a-zA-Z0-9_]+)?(?:\s+WHERE\s+(.*?))?(?:\s+GROUP\s+BY\s+(.*?))?(?:\s+ORDER\s+BY\s+(.*?))?(?:\s+LIMIT\s+(\d+))?$/i', $sql_norm, $m)) {
            $select_part = isset($m[1]) ? trim($m[1]) : '*';
            $where_part = isset($m[2]) ? trim($m[2]) : '';
            $groupby_part = isset($m[3]) ? trim($m[3]) : '';
            $orderby_part = isset($m[4]) ? trim($m[4]) : '';
            $limit_part = isset($m[5]) ? (int)$m[5] : 0;
        } else {
            if (preg_match('/SELECT\s+(.*?)\s+FROM/i', $sql_norm, $sm)) $select_part = trim($sm[1]);
            if (preg_match('/WHERE\s+(.*?)(?:\s+GROUP|\s+ORDER|\s+LIMIT|$)/i', $sql_norm, $wm)) $where_part = trim($wm[1]);
            if (preg_match('/GROUP\s+BY\s+(.*?)(?:\s+ORDER|\s+LIMIT|$)/i', $sql_norm, $gm)) $groupby_part = trim($gm[1]);
            if (preg_match('/ORDER\s+BY\s+(.*?)(?:\s+LIMIT|$)/i', $sql_norm, $om)) $orderby_part = trim($om[1]);
            if (preg_match('/LIMIT\s+(\d+)/i', $sql_norm, $lm)) $limit_part = (int)$lm[1];
        }

        $filtered = array();
        foreach ($rows as $r) {
            if (eval_where_clause_native($where_part, $r)) {
                $filtered[] = $r;
            }
        }

        $has_groupby = !empty($groupby_part);
        $has_aggregate = preg_match('/\b(COUNT|SUM|AVG|MIN|MAX)\s*\(/i', $select_part);
        $result_rows = array();

        if ($has_groupby || $has_aggregate) {
            $groups = array();
            $group_cols = array();
            if ($has_groupby) {
                foreach (explode(',', $groupby_part) as $gc) {
                    $gc = trim(str_replace(array('`', '"', "'"), '', $gc));
                    if (!empty($gc)) $group_cols[] = $gc;
                }
            }

            if (empty($group_cols)) {
                $groups['__all__'] = $filtered;
            } else {
                foreach ($filtered as $r) {
                    $k_parts = array();
                    foreach ($group_cols as $gc) {
                        $k_parts[] = (string)get_row_field_val_fuzzy($r, $gc);
                    }
                    $gk = implode('|||', $k_parts);
                    if (!isset($groups[$gk])) $groups[$gk] = array();
                    $groups[$gk][] = $r;
                }
            }

            $exprs = parse_select_exprs_native($select_part);

            foreach ($groups as $gk => $grows) {
                $out_row = array();
                $first_r = isset($grows[0]) ? $grows[0] : array();
                foreach ($exprs as $ex) {
                    $alias = $ex['alias'];
                    if ($ex['type'] === 'count_all') {
                        $out_row[$alias] = count($grows);
                    } elseif ($ex['type'] === 'sum') {
                        $s = 0.0;
                        $col = $ex['col'];
                        foreach ($grows as $gr) {
                            $raw_v = get_row_field_val_fuzzy($gr, $col);
                            $s += clean_numeric_value($raw_v);
                        }
                        $out_row[$alias] = round($s, 2);
                    } elseif ($ex['type'] === 'avg') {
                        $s = 0.0;
                        $cnt = count($grows);
                        $col = $ex['col'];
                        foreach ($grows as $gr) {
                            $raw_v = get_row_field_val_fuzzy($gr, $col);
                            $s += clean_numeric_value($raw_v);
                        }
                        $out_row[$alias] = $cnt > 0 ? round($s / $cnt, 2) : 0.0;
                    } elseif ($ex['type'] === 'max') {
                        $col = $ex['col'];
                        $vals = array();
                        foreach ($grows as $gr) $vals[] = clean_numeric_value(get_row_field_val_fuzzy($gr, $col));
                        $out_row[$alias] = !empty($vals) ? max($vals) : 0;
                    } elseif ($ex['type'] === 'min') {
                        $col = $ex['col'];
                        $vals = array();
                        foreach ($grows as $gr) $vals[] = clean_numeric_value(get_row_field_val_fuzzy($gr, $col));
                        $out_row[$alias] = !empty($vals) ? min($vals) : 0;
                    } else {
                        $col = $ex['col'];
                        $out_row[$alias] = get_row_field_val_fuzzy($first_r, $col);
                    }
                }
                $result_rows[] = $out_row;
            }
        } else {
            if ($select_part === '*' || empty($select_part)) {
                $result_rows = $filtered;
            } else {
                $exprs = parse_select_exprs_native($select_part);
                foreach ($filtered as $r) {
                    $out_row = array();
                    foreach ($exprs as $ex) {
                        $col = $ex['col'];
                        $alias = $ex['alias'];
                        $out_row[$alias] = get_row_field_val_fuzzy($r, $col);
                    }
                    $result_rows[] = $out_row;
                }
            }
        }

        if (!empty($orderby_part) && !empty($result_rows)) {
            $is_desc = (stripos($orderby_part, 'DESC') !== false);
            $clean_order = trim(preg_replace('/\s+(ASC|DESC)$/i', '', str_replace(array('`', '"', "'"), '', $orderby_part)));

            usort($result_rows, function ($a, $b) use ($clean_order, $is_desc) {
                $vA = isset($a[$clean_order]) ? $a[$clean_order] : (isset(array_values($a)[1]) ? array_values($a)[1] : array_values($a)[0]);
                $vB = isset($b[$clean_order]) ? $b[$clean_order] : (isset(array_values($b)[1]) ? array_values($b)[1] : array_values($b)[0]);

                if (is_numeric($vA) && is_numeric($vB)) {
                    $cmp = ($vA == $vB) ? 0 : (($vA < $vB) ? -1 : 1);
                } else {
                    $cmp = strcasecmp((string)$vA, (string)$vB);
                }
                return $is_desc ? -$cmp : $cmp;
            });
        }

        if ($limit_part > 0) {
            $result_rows = array_slice($result_rows, 0, $limit_part);
        }

        return $result_rows;
    }
}

// --- FUNÇÃO: PROCESSADOR DE CSV HÍBRIDO ---
if (!function_exists('process_uploaded_csv')) {
    function process_uploaded_csv($file_tmp_path, $original_filename)
    {
        $handle = fopen($file_tmp_path, "r");
        if (!$handle) throw new Exception("Não foi possível ler o arquivo CSV enviado.");

        $first_line = fgets($handle);
        rewind($handle);
        $delimiters = array(",", ";", "\t", "|");
        $chosen_delimiter = ",";
        $max_count = 0;
        foreach ($delimiters as $delim) {
            $c = substr_count($first_line, $delim);
            if ($c > $max_count) {
                $max_count = $c;
                $chosen_delimiter = $delim;
            }
        }

        $raw_headers = fgetcsv($handle, 0, $chosen_delimiter);
        if (!$raw_headers) throw new Exception("Cabeçalhos do CSV inválidos ou arquivo vazio.");

        $columns = array();
        $col_names_map = array();
        foreach ($raw_headers as $idx => $header) {
            $clean_name = preg_replace('/[^a-zA-Z0-9_]/', '_', strtolower(trim((string)$header)));
            if (empty($clean_name) || is_numeric($clean_name[0])) {
                $clean_name = "col_" . ($idx + 1) . "_" . $clean_name;
            }
            $clean_name = rtrim($clean_name, '_');
            $columns[] = "`$clean_name` TEXT";
            $col_names_map[] = $clean_name;
        }

        $rows_data = array();
        while (($row = fgetcsv($handle, 0, $chosen_delimiter)) !== false) {
            if (count($row) === count($col_names_map)) {
                $clean_row = array();
                foreach ($row as $i => $val) {
                    $val_utf8 = (function_exists('mb_check_encoding') && mb_check_encoding($val, 'UTF-8')) ? $val : utf8_encode($val);
                    $clean_row[$col_names_map[$i]] = $val_utf8;
                }
                $rows_data[] = $clean_row;
            }
        }
        fclose($handle);

        $ddl_generated = "CREATE TABLE `dados_csv` (\n";
        foreach ($col_names_map as $i => $cn) {
            $orig = isset($raw_headers[$i]) ? addslashes($raw_headers[$i]) : $cn;
            $ddl_generated .= "  `$cn` TEXT COMMENT '$orig',\n";
        }
        $ddl_generated = rtrim($ddl_generated, ",\n") . "\n) COMMENT 'Tabela gerada do arquivo CSV: " . addslashes($original_filename) . "';";

        $session_dir = sys_get_temp_dir();
        $sqlite_file = $session_dir . DIRECTORY_SEPARATOR . 'bi_csv_' . session_id() . '.sqlite';
        $engine_used = 'native_php';

        if (extension_loaded('pdo_sqlite')) {
            try {
                if (file_exists($sqlite_file)) @unlink($sqlite_file);
                $pdo = new PDO("sqlite:" . $sqlite_file);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $pdo->exec("CREATE TABLE `dados_csv` (" . implode(", ", $columns) . ");");
                $placeholders = implode(",", array_fill(0, count($col_names_map), "?"));
                $stmt = $pdo->prepare("INSERT INTO `dados_csv` VALUES ($placeholders)");
                $pdo->beginTransaction();
                foreach ($rows_data as $r) {
                    $stmt->execute(array_values($r));
                }
                $pdo->commit();
                $engine_used = 'pdo_sqlite';
            } catch (Exception $e) {
                $engine_used = 'native_php';
            }
        } elseif (class_exists('SQLite3')) {
            try {
                if (file_exists($sqlite_file)) @unlink($sqlite_file);
                $db3 = new SQLite3($sqlite_file);
                $db3->exec("CREATE TABLE `dados_csv` (" . implode(", ", $columns) . ");");
                $placeholders = implode(",", array_fill(0, count($col_names_map), "?"));
                $stmt = $db3->prepare("INSERT INTO `dados_csv` VALUES ($placeholders)");
                $db3->exec('BEGIN TRANSACTION');
                foreach ($rows_data as $r) {
                    $idx = 1;
                    foreach ($r as $val) {
                        $stmt->bindValue($idx++, $val, SQLITE3_TEXT);
                    }
                    $stmt->execute();
                    $stmt->reset();
                }
                $db3->exec('COMMIT');
                $db3->close();
                $engine_used = 'sqlite3';
            } catch (Exception $e) {
                $engine_used = 'native_php';
            }
        }

        $data_file = '';
        if ($engine_used === 'native_php') {
            $data_file = $session_dir . DIRECTORY_SEPARATOR . 'bi_csv_data_' . session_id() . '.json';
            file_put_contents($data_file, json_encode($rows_data));
        }

        return array(
            'engine'       => $engine_used,
            'sqlite_file'  => $sqlite_file,
            'data_file'    => $data_file,
            'schema'       => $ddl_generated,
            'rows'         => count($rows_data),
            'columns'      => count($col_names_map),
            'filename'     => $original_filename,
            'sample_rows'  => array_slice($rows_data, 0, 5)
        );
    }
}

// --- FUNÇÃO: GERADOR DE PROMPT PARA O GEMINI ---
if (!function_exists('build_bi_prompt')) {
    function build_bi_prompt($active_schema, $history_context, $user_question, $sql_dialect)
    {
        $p = "Você é um analista de BI Senior especialista em $sql_dialect. Schema DDL da base de dados:\n";
        $p .= $active_schema . "\n";

        if (!empty($history_context)) {
            $p .= $history_context . "\n";
            $p .= "DIRETRIZ DE INDEPENDÊNCIA E HISTÓRICO:\n";
            $p .= "- O histórico acima serve EXCLUSIVAMENTE para refinamentos contínuos diretos (ex: 'agora em pizza', 'filtre apenas SP', 'e em 2024?').\n";
            $p .= "- Se a pergunta for sobre um assunto novo, uma entidade diferente, uma nova busca ou uma pergunta completa, IGNORE TOTALMENTE o histórico e crie a consulta DO ZERO.\n";
            $p .= "- NUNCA arraste filtros WHERE ou condições da consulta anterior se não foram expressamente solicitados na pergunta atual.\n\n";
        }

        $p .= "Pergunta do usuário: '$user_question'.\n\n";
        $p .= "REGRAS RÍGIDAS DE RETORNO:\n";
        $p .= "1. Gere consultas SQL válidas compatíveis exclusivamente com $sql_dialect.\n";
        $p .= "2. Apenas comandos de leitura estrita (SELECT ou WITH) são permitidos.\n";
        $p .= "3. Se a pergunta for sobre um NOME ou 'LISTAR' sem pedir análise de texto, use widget 'tabela' com 'SELECT *'.\n";
        $p .= "4. Se solicitar 'total', 'soma' ou contagem agregada única, use widget 'kpi'.\n";
        $p .= "5. REGRAS QUANTITATIVAS PARA GRÁFICOS:\n";
        $p .= "   - Para BARRA ou PIZZA: A consulta DEVE retornar 2 colunas: a 1ª como rótulo textual e a 2ª como número calculado (use COUNT(*), SUM(coluna) ou AVG(coluna)) com GROUP BY na categoria. Exemplo: SELECT categoria, COUNT(*) AS total FROM dados_csv GROUP BY categoria ORDER BY total DESC LIMIT 10;\n";
        $p .= "   - Para LINHA: Retorne 2 colunas: 1ª data/período e 2ª valor numérico agrupado com GROUP BY.\n";
        $p .= "   - Para KPI: Retorne 1 única linha com 1 único valor numérico (SUM, AVG ou COUNT(*)).\n";
        $p .= "   - Evite funções complexas de banco (como CAST, COALESCE ou STRFTIME). Use nomes diretos de colunas conforme o Schema DDL.\n";
        $p .= "6. Para GRAFO: 3 colunas (fonte, destino, valor).\n";
        $p .= "7. Para HEATMAP: 3 colunas (eixo_x, eixo_y, valor).\n";
        $p .= "8. Para TIMELINE (LINHA DO TEMPO): Retorne EXATAMENTE 3 colunas: (1) nome_evento, (2) data_inicio, (3) data_fim. A coluna 'nome_evento' DEVE ser estritamente um RÓTULO/TÍTULO CURTO E CONCISO (máximo 4 a 6 palavras). NUNCA insira textos longos na coluna 'nome_evento'.\n";
        $p .= "9. REGRA DE CONTINGÊNCIA ANALÍTICA: Se a pergunta solicitar 'análise', 'resumo', 'diagnóstico', for de teor investigativo/aberto, OU caso não haja dados quantitativos claros para um gráfico, selecione OBRIGATORIAMENTE o widget 'texto'. Gere uma consulta SELECT com as colunas necessárias para embasar o parecer analítico.\n";
        $p .= "10. Escolha a melhor visualização dentre: 'kpi|grafo|heatmap|nuvem|barra|pizza|linha|tabela|timeline|texto'. Se houver qualquer dúvida, use 'texto'.\n";
        $p .= "11. TRADUÇÃO SEMÂNTICA OBRIGATÓRIA: Forneça no objeto 'colunas' o mapeamento do nome técnico para um rótulo humanizado elegante.\n";
        $p .= "12. FORMATO OBRIGATÓRIO (JSON estrito):\n";
        $p .= "{'widgets': [{ 'sql':'', 'visualizacao':'kpi|grafo|heatmap|nuvem|barra|pizza|linha|tabela|timeline|texto', 'titulo':'', 'colunas':{'coluna_tecnica': 'Nome Legível'} }]}\n";

        return $p;
    }
}

// --- ROTEADOR BACKEND (POST HANDLER) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json; charset=utf-8');

    // AÇÃO: SALVAR CONFIGURAÇÃO DA IA (CHAVE E MODELO)
    if (isset($_GET['action']) && $_GET['action'] === 'save_ai_config') {
        try {
            $input = json_decode((string)file_get_contents('php://input'), true);
            $key_type = (isset($input['key_type']) && $input['key_type'] === 'custom') ? 'custom' : 'default';
            $custom_key = isset($input['custom_key']) ? trim((string)$input['custom_key']) : '';
            $selected_model = isset($input['model']) ? trim((string)$input['model']) : $gemini_model;

            if (!in_array($selected_model, $gemini_available_models)) {
                $selected_model = $gemini_model;
            }

            if ($key_type === 'custom' && empty($custom_key)) {
                throw new Exception("Informe a sua chave de API pessoal do Gemini para ativar esta opção.");
            }

            $_SESSION['ai_config'] = array(
                'key_type'   => $key_type,
                'custom_key' => $custom_key,
                'model'      => $selected_model
            );

            if (isset($_SESSION['query_cache'])) unset($_SESSION['query_cache']);

            echo json_encode(array(
                'status'   => 'success',
                'message'  => 'Configurações de IA salvas com sucesso!',
                'model'    => $selected_model,
                'key_type' => $key_type
            ));
        } catch (Exception $e) {
            echo json_encode(array('status' => 'error', 'message' => $e->getMessage()));
        }
        exit;
    }

    // AÇÃO: PERSISTIR SELEÇÃO DA FONTE ATIVA
    if (isset($_GET['action']) && $_GET['action'] === 'set_active_source') {
        $input = json_decode((string)file_get_contents('php://input'), true);
        $new_source = isset($input['source']) ? trim((string)$input['source']) : 'default';
        if (in_array($new_source, array('default', 'custom', 'csv'))) {
            $_SESSION['active_db_source'] = $new_source;
        }
        echo json_encode(array('status' => 'success'));
        exit;
    }

    // AÇÃO: UPLOAD DE CSV (COM GERAÇÃO AUTOMÁTICA DE PERGUNTAS E DASHBOARD)
    if (isset($_GET['action']) && $_GET['action'] === 'upload_csv') {
        try {
            if (!$enable_csv_upload) throw new Exception("O módulo de planilhas CSV está desabilitado.");
            if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception("Falha no envio do arquivo CSV.");
            }
            $res_csv = process_uploaded_csv($_FILES['csv_file']['tmp_name'], $_FILES['csv_file']['name']);

            $sample_json = !empty($res_csv['sample_rows']) ? json_encode($res_csv['sample_rows']) : "";
            $generated_examples = generate_ai_suggested_questions($res_csv['schema'], $active_gemini_key, $active_gemini_model, $sample_json);
            $res_csv['examples'] = $generated_examples;

            $_SESSION['csv_storage'] = $res_csv;
            $_SESSION['active_db_source'] = 'csv';

            $notice = '';
            if ($res_csv['engine'] === 'native_php') {
                $notice = "<strong>Aviso Técnico:</strong> A extensão <code>pdo_sqlite</code> não está ativa no servidor. O arquivo foi estruturado com o <strong>Motor Nativo PHP Otimizado</strong>.<br><br>As análises quantitativas e gráficos funcionarão com conversão inteligente em memória, porém recomendamos que o administrador ative o módulo <code>pdo_sqlite</code> para consultas em arquivos gigantes com máxima performance.";
            }

            echo json_encode(array(
                'status'   => 'success',
                'engine'   => $res_csv['engine'],
                'message'  => "Arquivo <strong>" . htmlspecialchars($res_csv['filename']) . "</strong> pronto! " . $res_csv['rows'] . " registros importados (" . $res_csv['columns'] . " colunas).",
                'notice'   => $notice,
                'examples' => $generated_examples,
                'data'     => $res_csv
            ));
        } catch (Exception $e) {
            echo json_encode(array('status' => 'error', 'message' => $e->getMessage()));
        }
        exit;
    }

    // AÇÃO: CONECTAR BASE CUSTOMIZADA (COM GERAÇÃO AUTOMÁTICA DE PERGUNTAS E DASHBOARD)
    if (isset($_GET['action']) && $_GET['action'] === 'save_custom_db') {
        try {
            if (!$enable_custom_db) throw new Exception("A conexão com bases customizadas está desabilitada.");
            $input = json_decode((string)file_get_contents('php://input'), true);
            $c_host = isset($input['host']) ? trim($input['host']) : '';
            $c_port = (isset($input['port']) && !empty($input['port'])) ? (int)$input['port'] : 3306;
            $c_user = isset($input['user']) ? trim($input['user']) : '';
            $c_pass = isset($input['pass']) ? trim($input['pass']) : '';
            $c_name = isset($input['name']) ? trim($input['name']) : '';

            if (empty($c_host) || empty($c_user) || empty($c_name)) {
                throw new Exception("Informe ao menos o Servidor, Usuário e Nome do Banco de Dados.");
            }

            $cfg_custom = array('host' => $c_host, 'port' => $c_port, 'user' => $c_user, 'pass' => $c_pass, 'name' => $c_name);
            $test_conn = create_safe_mysql_connection($cfg_custom);
            $extracted = extract_mysql_schema_auto($test_conn);
            $test_conn->close();

            $generated_examples = generate_ai_suggested_questions($extracted['schema'], $active_gemini_key, $active_gemini_model, "");

            $_SESSION['custom_db'] = array(
                'config'       => $cfg_custom,
                'schema'       => $extracted['schema'],
                'tables_count' => $extracted['tables_count'],
                'examples'     => $generated_examples
            );
            $_SESSION['active_db_source'] = 'custom';

            echo json_encode(array(
                'status'       => 'success',
                'message'      => "Conexão realizada com sucesso em modo SOMENTE LEITURA! (" . $extracted['tables_count'] . " tabelas disponíveis)",
                'tables_count' => $extracted['tables_count'],
                'db_name'      => $c_name,
                'db_host'      => $c_host,
                'examples'     => $generated_examples
            ));
        } catch (Exception $e) {
            echo json_encode(array('status' => 'error', 'message' => $e->getMessage()));
        }
        exit;
    }

    // AÇÃO PRINCIPAL: EXECUÇÃO DA CONSULTA ANALÍTICA
    try {
        $raw_input = file_get_contents('php://input');
        $post_data = json_decode((string)$raw_input, true);
        $user_question = (is_array($post_data) && isset($post_data['prompt'])) ? trim((string)$post_data['prompt']) : '';

        $selected_source = 'default';
        if (isset($post_data['source'])) {
            $selected_source = trim((string)$post_data['source']);
        } elseif (isset($_SESSION['active_db_source'])) {
            $selected_source = $_SESSION['active_db_source'];
        } elseif (!$enable_default_db) {
            $selected_source = $enable_custom_db ? 'custom' : ($enable_csv_upload ? 'csv' : 'none');
        }

        if (empty($user_question)) throw new Exception("Pergunta vazia.");

        $active_engine = 'mysql';
        $active_schema = $database_schema;
        $active_conn_info = $db_config;
        $active_source_label = 'Base Padrão';

        if ($selected_source === 'csv') {
            if (!$enable_csv_upload) throw new Exception("Módulo CSV desabilitado.");
            if (empty($_SESSION['csv_storage'])) {
                throw new Exception("Nenhum arquivo CSV ativo. Envie um arquivo primeiro.");
            }
            $active_engine = $_SESSION['csv_storage']['engine'];
            $active_schema = $_SESSION['csv_storage']['schema'];
            $active_source_label = 'CSV: ' . $_SESSION['csv_storage']['filename'];
        } elseif ($selected_source === 'custom') {
            if (!$enable_custom_db) throw new Exception("Bases customizadas desabilitadas.");
            if (empty($_SESSION['custom_db']['config'])) {
                throw new Exception("A Base Customizada ainda não foi conectada. Informe os dados de acesso.");
            }
            $active_engine = 'mysql';
            $active_schema = $_SESSION['custom_db']['schema'];
            $active_conn_info = $_SESSION['custom_db']['config'];
            $active_source_label = 'Base Customizada: ' . $active_conn_info['name'];
        } else {
            if (!$enable_default_db) {
                throw new Exception("A Base Padrão está desabilitada. Conecte uma Base Externa ou envie um arquivo CSV para começar.");
            }
            if (empty(trim($active_schema))) {
                if (empty($_SESSION['default_db_schema'])) {
                    $conn_def = create_safe_mysql_connection($active_conn_info);
                    $extracted_def = extract_mysql_schema_auto($conn_def);
                    $conn_def->close();
                    $_SESSION['default_db_schema'] = $extracted_def['schema'];
                }
                $active_schema = $_SESSION['default_db_schema'];
            }
        }

        // --- CACHE DE CONSULTAS ---
        $cache_key = md5($selected_source . '|' . $active_gemini_model . '|' . mb_strtolower($user_question));
        $cached_widgets_config = (isset($_SESSION['query_cache']) && isset($_SESSION['query_cache'][$cache_key])) ? $_SESSION['query_cache'][$cache_key] : null;

        $used_history = false;
        if (!$cached_widgets_config) {
            if (!empty($enable_structured_rag) && (int)$enable_structured_rag === 1 && $active_engine === 'mysql') {
                $rag_router_prompt = "Você é um especialista em Bancos de Dados Relacionais e Schema Pruning.
Analise a pergunta do usuário e o Schema DDL completo para RETORNAR APENAS as tabelas e colunas necessárias para responder à dúvida.
REGRAS: Retorne exclusivamente comandos CREATE TABLE puros sem explicações adicionais.

Schema Completo:
$active_schema

Pergunta:
'$user_question'";

                $res_rag = call_gemini_api(array(
                    "contents" => array(array("parts" => array(array("text" => $rag_router_prompt)))),
                    "generationConfig" => array("temperature" => 0.0)
                ), $active_gemini_key, $active_gemini_model);

                $rag_ddl_text = (isset($res_rag['candidates'][0]['content']['parts'][0]['text'])) ? trim((string)$res_rag['candidates'][0]['content']['parts'][0]['text']) : '';
                $rag_ddl_text = preg_replace('/^' . TICK3 . '(?:sql)?\s*|\s*' . TICK3 . '$/i', '', $rag_ddl_text);
                if (!empty($rag_ddl_text)) $active_schema = trim($rag_ddl_text);
            }

            $history_context = "";
            if (!empty($_SESSION['chat_history']) && is_array($_SESSION['chat_history'])) {
                $history_context = "Histórico recente de perguntas e comandos executados:\n";
                foreach (array_slice($_SESSION['chat_history'], -3) as $turn) {
                    $history_context .= "- Pergunta: '" . $turn['q'] . "' -> SQL: '" . $turn['sql'] . "'\n";
                }
                $used_history = true;
            }

            $sql_dialect = ($active_engine === 'mysql') ? 'MySQL' : 'SQL Padrão ANSI (tabela: dados_csv)';
            $prompt_text = build_bi_prompt($active_schema, $history_context, $user_question, $sql_dialect);

            $payload = array(
                "contents" => array(array("parts" => array(array("text" => $prompt_text)))),
                "generationConfig" => array("response_mime_type" => "application/json", "temperature" => 0.1)
            );

            $res_ai = call_gemini_api($payload, $active_gemini_key, $active_gemini_model);
            $intent_json = isset($res_ai['candidates'][0]['content']['parts'][0]['text']) ? $res_ai['candidates'][0]['content']['parts'][0]['text'] : null;
            $intent_decoded = json_decode((string)$intent_json, true);
            $widgets_config = (is_array($intent_decoded) && isset($intent_decoded['widgets'])) ? $intent_decoded['widgets'] : array();
        } else {
            $widgets_config = $cached_widgets_config;
        }

        // Executor unificado de widgets
        $execute_widgets_flow = function ($w_list) use ($active_engine, $active_conn_info, $active_gemini_key, $active_gemini_model, $user_question) {
            $final_widgets = array();
            $total_rows_count = 0;
            $valid_visualizations = array('kpi', 'grafo', 'heatmap', 'nuvem', 'barra', 'pizza', 'linha', 'tabela', 'timeline', 'texto');

            $csv_rows_all = array();
            if ($active_engine === 'native_php') {
                $raw_rows_json = file_get_contents($_SESSION['csv_storage']['data_file']);
                $csv_rows_all = json_decode((string)$raw_rows_json, true);
                if (!is_array($csv_rows_all)) $csv_rows_all = array();
            }

            $mysql_conn = null;
            if ($active_engine === 'mysql') {
                $mysql_conn = create_safe_mysql_connection($active_conn_info);
            }

            foreach ($w_list as $w) {
                if (!isset($w['sql']) || !is_sql_safe($w['sql'])) continue;

                $w_vis = isset($w['visualizacao']) ? strtolower(trim((string)$w['visualizacao'])) : 'texto';
                if (!in_array($w_vis, $valid_visualizations)) {
                    $w_vis = 'texto';
                }
                $w['visualizacao'] = $w_vis;

                $rows = array();
                if ($active_engine === 'pdo_sqlite') {
                    $pdo = new PDO("sqlite:" . $_SESSION['csv_storage']['sqlite_file']);
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    $stmt = $pdo->query($w['sql']);
                    $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : array();
                } elseif ($active_engine === 'sqlite3') {
                    $db3 = new SQLite3($_SESSION['csv_storage']['sqlite_file']);
                    $res_s3 = $db3->query($w['sql']);
                    if ($res_s3) while ($r = $res_s3->fetchArray(SQLITE3_ASSOC)) $rows[] = $r;
                    $db3->close();
                } elseif ($active_engine === 'native_php') {
                    $rows = execute_native_php_query($w['sql'], $csv_rows_all);

                    // CONTINGÊNCIA QUANTITATIVA: Se o gráfico (barra/pizza) recebeu apenas 1 coluna de categorias
                    // e nenhuma coluna numérica de contagem, o motor PHP calcula as frequências automaticamente!
                    if (($w_vis === 'barra' || $w_vis === 'pizza') && !empty($rows)) {
                        $first_row = $rows[0];
                        if (count($first_row) === 1) {
                            $cat_key = array_keys($first_row)[0];
                            $counts = array();
                            foreach ($rows as $r_item) {
                                $cat_val = (string)$r_item[$cat_key];
                                $counts[$cat_val] = isset($counts[$cat_val]) ? $counts[$cat_val] + 1 : 1;
                            }
                            arsort($counts);
                            $synthesized_rows = array();
                            foreach (array_slice($counts, 0, 10, true) as $k_val => $v_count) {
                                $synthesized_rows[] = array(
                                    $cat_key => $k_val,
                                    'total'  => $v_count
                                );
                            }
                            $rows = $synthesized_rows;
                        }
                    }
                } else {
                    $res_db = $mysql_conn->query($w['sql']);
                    if ($res_db && $res_db instanceof mysqli_result) {
                        while ($r = $res_db->fetch_assoc()) $rows[] = $r;
                        $res_db->free();
                    }
                }

                $total_rows_count += count($rows);

                // GERAÇÃO DA ANÁLISE EM TEXTO
                $html_analysis = "";
                if ($w_vis === 'texto') {
                    $sample_data = !empty($rows) ? json_encode(array_slice($rows, 0, 30)) : "Nenhum dado retornado.";
                    $p_narrative = "Você é um consultor analítico sênior de BI.
Responda diretamente e com profundidade à pergunta do usuário: '{$user_question}'.
Utilize os registros a seguir para fazer diagnósticos, destacar números relevantes, identificar correlações e emitir recomendações executivas.
REGRAS: Formate toda a resposta EXCLUSIVAMENTE em código HTML limpo contido em uma div (utilize <h3>, <p>, <ul>, <li>, <strong>). Não inclua delimitadores markdown.

Dados Brutos Retornados: " . $sample_data;

                    $res_n = call_gemini_api(array("contents" => array(array("parts" => array(array("text" => $p_narrative))))), $active_gemini_key, $active_gemini_model);
                    $raw_n = isset($res_n['candidates'][0]['content']['parts'][0]['text']) ? $res_n['candidates'][0]['content']['parts'][0]['text'] : '';
                    $html_analysis = preg_replace('/^' . TICK3 . '(?:html)?\s*|\s*' . TICK3 . '$/i', '', trim((string)$raw_n));
                    if (empty($html_analysis)) {
                        $html_analysis = "<p>Não foi possível sintetizar a análise textual dos registros.</p>";
                    }
                }

                $final_widgets[] = array('config' => $w, 'data' => $rows, 'analysis_html' => $html_analysis);
            }

            if ($mysql_conn) $mysql_conn->close();

            return array('widgets' => $final_widgets, 'total_rows' => $total_rows_count);
        };

        // Primeira Execução
        $exec_result = $execute_widgets_flow($widgets_config);
        $final_widgets = $exec_result['widgets'];

        // AUTO-RECOVERY: Se não encontrou dados e havia histórico, refaz a pesquisa DO ZERO sem histórico
        if ($exec_result['total_rows'] === 0 && $used_history) {
            $prompt_fresh = build_bi_prompt($active_schema, "", $user_question, $sql_dialect);
            $res_ai_fresh = call_gemini_api(array(
                "contents" => array(array("parts" => array(array("text" => $prompt_fresh)))),
                "generationConfig" => array("response_mime_type" => "application/json", "temperature" => 0.1)
            ), $active_gemini_key, $active_gemini_model);

            $intent_fresh_json = isset($res_ai_fresh['candidates'][0]['content']['parts'][0]['text']) ? $res_ai_fresh['candidates'][0]['content']['parts'][0]['text'] : null;
            $intent_fresh_decoded = json_decode((string)$intent_fresh_json, true);
            $widgets_fresh_config = (is_array($intent_fresh_decoded) && isset($intent_fresh_decoded['widgets'])) ? $intent_fresh_decoded['widgets'] : array();

            if (!empty($widgets_fresh_config)) {
                $exec_fresh_result = $execute_widgets_flow($widgets_fresh_config);
                if ($exec_fresh_result['total_rows'] > 0) {
                    $final_widgets = $exec_fresh_result['widgets'];
                    $widgets_config = $widgets_fresh_config;
                }
            }
        }

        // Fallback final de segurança
        if (empty($final_widgets)) {
            $fallback_sql = ($active_engine === 'mysql') ? "SELECT * FROM `dados` LIMIT 25" : "SELECT * FROM dados_csv LIMIT 25";
            if ($active_engine === 'mysql' && preg_match('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?([a-zA-Z0-9_]+)`?/i', $active_schema, $table_match)) {
                $fallback_sql = "SELECT * FROM `" . $table_match[1] . "` LIMIT 25";
            }
            $exec_fallback = $execute_widgets_flow(array(
                array('sql' => $fallback_sql, 'visualizacao' => 'texto', 'titulo' => 'Análise Textual dos Registros', 'colunas' => array())
            ));
            $final_widgets = $exec_fallback['widgets'];
        }

        // Salva cache e histórico de diálogo
        if (!empty($widgets_config)) {
            if (!isset($_SESSION['query_cache'])) $_SESSION['query_cache'] = array();
            $_SESSION['query_cache'][$cache_key] = $widgets_config;

            $last_sql = isset($widgets_config[0]['sql']) ? $widgets_config[0]['sql'] : '';
            if (!empty($last_sql)) {
                if (!isset($_SESSION['chat_history'])) $_SESSION['chat_history'] = array();
                $_SESSION['chat_history'][] = array('q' => $user_question, 'sql' => $last_sql);
                if (count($_SESSION['chat_history']) > 6) array_shift($_SESSION['chat_history']);
            }
        }

        echo json_encode(array(
            'status' => 'success',
            'source' => $active_source_label,
            'cached' => ($cached_widgets_config !== null),
            'widgets' => $final_widgets
        ));
    } catch (Exception $e) {
        echo json_encode(array('status' => 'error', 'message' => $e->getMessage()));
    }
    exit;
}

// Resolução de status e arrays de perguntas para o frontend
$cur_active_source = 'default';
if (isset($_SESSION['active_db_source'])) {
    $cur_active_source = $_SESSION['active_db_source'];
} elseif (!$enable_default_db) {
    if ($enable_custom_db) {
        $cur_active_source = 'custom';
    } elseif ($enable_csv_upload) {
        $cur_active_source = 'csv';
    }
}

$has_custom_db = !empty($_SESSION['custom_db']['config']);
$custom_db_info = $has_custom_db ? $_SESSION['custom_db']['config']['name'] . ' (' . $_SESSION['custom_db']['config']['host'] . ')' : '';
$custom_db_examples = isset($_SESSION['custom_db']['examples']) ? $_SESSION['custom_db']['examples'] : array();

$has_csv = !empty($_SESSION['csv_storage']);
$csv_info = $has_csv ? $_SESSION['csv_storage']['filename'] . ' (' . $_SESSION['csv_storage']['rows'] . ' registros)' : '';
$csv_examples = isset($_SESSION['csv_storage']['examples']) ? $_SESSION['csv_storage']['examples'] : array();

$default_db_info = $db_config['name'] . ' (' . $db_config['host'] . ')';
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>IA Análises Banco de Dados</title>
    <!-- Bibliotecas: ECharts, WordCloud, FontAwesome & HTML2PDF -->
    <script src="https://cdn.jsdelivr.net/npm/echarts@5.4.3/dist/echarts.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/echarts-wordcloud@2.1.0/dist/echarts-wordcloud.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #1e40af;
            --secondary: #3b82f6;
            --success: #059669;
            --bg: #f1f5f9;
            --sidebar: #0f172a;
            --border: #e2e8f0;
        }

        body {
            font-family: 'Inter', sans-serif;
            margin: 0;
            display: flex;
            height: 100vh;
            background: #e5e7eb;
            overflow: hidden;
        }

        #sidebar {
            width: 300px;
            min-width: 300px;
            background: var(--sidebar);
            color: white;
            display: flex;
            flex-direction: column;
            z-index: 2500;
            transition: all 0.3s ease;
            box-shadow: 4px 0 15px rgba(0, 0, 0, 0.2);
        }

        #sidebar.collapsed {
            margin-left: -300px;
        }

        .side-header {
            padding: 20px;
            font-weight: 800;
            background: #1e293b;
            border-bottom: 1px solid #0f172a;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .side-content {
            padding: 15px;
            overflow-y: auto;
            flex: 1;
        }

        .side-section-title {
            font-size: 10px;
            color: #94a3b8;
            margin: 15px 0 8px 0;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .tool-btn {
            display: block;
            width: 100%;
            padding: 11px 13px;
            margin-bottom: 8px;
            background: rgba(59, 130, 246, 0.15);
            border: 1px solid rgba(59, 130, 246, 0.3);
            border-radius: 8px;
            text-align: left;
            color: #93c5fd;
            cursor: pointer;
            font-size: 12px;
            font-weight: bold;
            transition: 0.2s;
        }

        .tool-btn:hover {
            background: var(--secondary);
            color: white;
            border-color: var(--secondary);
        }

        .example-btn {
            display: block;
            width: 100%;
            padding: 11px 13px;
            margin-bottom: 8px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            text-align: left;
            color: #cbd5e1;
            cursor: pointer;
            font-size: 12px;
            transition: 0.2s;
            line-height: 1.4;
            word-break: break-word;
        }

        .example-btn:hover {
            background: var(--secondary);
            color: white;
        }

        #sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 2400;
            display: none;
            backdrop-filter: blur(2px);
        }

        #chat-main {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: #f1f5f9;
            min-width: 0;
            position: relative;
            overflow: hidden;
        }

        .top-bar {
            position: sticky;
            top: 0;
            padding: 10px 20px;
            border-bottom: 1px solid #cbd5e1;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: white;
            z-index: 500;
            gap: 10px;
            flex-wrap: wrap;
        }

        .top-bar-left {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .top-bar-right {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .toggle-btn {
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            padding: 8px 14px;
            border-radius: 8px;
            cursor: pointer;
            color: #475569;
        }

        /* BADGE INDICADOR DA FONTE ATIVA */
        .active-source-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #0f172a;
            color: #f8fafc;
            padding: 6px 14px;
            border-radius: 30px;
            font-size: 11.5px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .status-indicator-dot {
            width: 8px;
            height: 8px;
            background-color: #10b981;
            border-radius: 50%;
            display: inline-block;
            box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.2);
            animation: pulse-dot 2s infinite;
        }

        @keyframes pulse-dot {
            0% {
                transform: scale(0.95);
                box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
            }

            70% {
                transform: scale(1);
                box-shadow: 0 0 0 6px rgba(16, 185, 129, 0);
            }

            100% {
                transform: scale(0.95);
                box-shadow: 0 0 0 0 rgba(16, 185, 129, 0);
            }
        }

        .pill-title {
            color: #94a3b8;
            font-size: 9.5px;
            font-weight: 800;
            letter-spacing: 0.5px;
        }

        /* BADGE INTERATIVO DA IA */
        .active-ai-pill {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            background: #eff6ff;
            color: var(--primary);
            border: 1px solid #bfdbfe;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 11.5px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
        }

        .active-ai-pill:hover {
            background: #dbeafe;
            border-color: var(--secondary);
        }

        .db-selector-box {
            display: flex;
            align-items: center;
            background: #f8fafc;
            border: 1.5px solid #cbd5e1;
            border-radius: 10px;
            padding: 4px 8px;
            gap: 6px;
        }

        .db-select {
            border: none;
            background: transparent;
            font-size: 12px;
            font-weight: 700;
            color: #1e293b;
            outline: none;
            cursor: pointer;
        }

        .btn-db-action {
            background: #e2e8f0;
            border: none;
            padding: 5px 10px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: bold;
            cursor: pointer;
            color: #334155;
            transition: 0.2s;
        }

        .btn-db-action:hover {
            background: var(--secondary);
            color: white;
        }

        .btn-pdf-export {
            background: #e11d48;
            color: white;
            border: none;
            padding: 7px 14px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 800;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: 0.2s;
        }

        .btn-pdf-export:hover {
            background: #be123c;
        }

        .btn-reset {
            color: #dc2626;
            text-decoration: none;
            font-size: 11px;
            font-weight: 800;
            padding: 7px 14px;
            border: 1.5px solid #fecaca;
            border-radius: 20px;
            background: white;
        }

        .btn-logout {
            color: #475569;
            text-decoration: none;
            font-size: 11px;
            font-weight: 800;
            padding: 7px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 20px;
            background: white;
        }

        .btn-logout:hover {
            background: #f8fafc;
            color: #dc2626;
        }

        #chat-log {
            flex: 1;
            overflow-y: auto;
            padding: 25px;
            display: flex;
            flex-direction: column;
            gap: 25px;
            scroll-behavior: smooth;
        }

        /* BLOCO INTEGRADO DE PERGUNTA E RESPOSTA (CHAT-TURN-BLOCK) */
        .chat-turn-block {
            display: flex;
            flex-direction: column;
            gap: 12px;
            width: 100%;
            position: relative;
            transition: opacity 0.25s ease, transform 0.25s ease;
        }

        .msg-user-row {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 8px;
            width: 100%;
            margin-bottom: 2px;
        }

        .msg-user-bubble {
            background: var(--primary);
            color: white;
            padding: 14px 20px;
            border-radius: 18px 18px 4px 18px;
            max-width: 85%;
            font-size: 14px;
            line-height: 1.5;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            word-break: break-word;
        }

        .source-tag {
            font-size: 10px;
            background: rgba(255, 255, 255, 0.25);
            padding: 3px 8px;
            border-radius: 10px;
            margin-left: 8px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .user-copy-btn,
        .user-del-btn {
            background: white;
            border: 1px solid var(--border);
            color: #64748b;
            cursor: pointer;
            padding: 8px 12px;
            border-radius: 10px;
            font-size: 0.95rem;
            transition: all 0.2s ease;
            flex-shrink: 0;
        }

        .user-copy-btn:hover {
            background: #f8fafc;
            color: var(--primary);
            border-color: var(--secondary);
        }

        .user-del-btn {
            color: #94a3b8;
            padding: 8px 11px;
            font-size: 0.85rem;
        }

        .user-del-btn:hover {
            background: #fee2e2;
            color: #dc2626;
            border-color: #fca5a5;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(440px, 1fr));
            gap: 20px;
            width: 100%;
        }

        .card {
            background: white;
            border-radius: 18px;
            border: 1px solid var(--border);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
            padding: 22px;
            position: relative;
            animation: slideIn 0.3s;
            overflow: hidden;
            min-width: 0;
            display: flex;
            flex-direction: column;
        }

        .card-title {
            font-weight: 800;
            color: #1e293b;
            margin-bottom: 18px;
            border-left: 5px solid var(--secondary);
            padding-left: 12px;
            font-size: 0.95rem;
            padding-right: 45px;
            line-height: 1.4;
        }

        .copy-btn {
            position: absolute;
            top: 16px;
            right: 16px;
            background: #f8fafc;
            border: 1px solid var(--border);
            color: #64748b;
            cursor: pointer;
            padding: 7px 10px;
            border-radius: 8px;
            font-size: 0.95rem;
            transition: 0.2s;
        }

        .copy-btn:hover {
            background: #eff6ff;
            color: var(--primary);
        }

        .analysis-text {
            color: #1e293b;
            font-size: 13.5px;
            line-height: 1.7;
        }

        .analysis-text h3 {
            color: var(--primary);
            font-size: 1.1rem;
            margin-top: 5px;
            margin-bottom: 10px;
            font-weight: 800;
        }

        .analysis-text ul {
            padding-left: 18px;
            margin-bottom: 10px;
        }

        .table-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            gap: 10px;
            background: #f8fafc;
            padding: 10px 12px;
            border-radius: 12px;
            border: 1px solid var(--border);
        }

        .search-table {
            padding: 8px 12px;
            border: 1.5px solid #cbd5e1;
            border-radius: 8px;
            font-size: 12px;
            flex: 1;
            outline: none;
            background: white;
        }

        .export-btn {
            background: var(--success);
            color: white;
            border: none;
            padding: 8px 14px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 11px;
            font-weight: 800;
        }

        .record-counter {
            font-size: 11px;
            color: #64748b;
            font-weight: 800;
            background: #e2e8f0;
            padding: 4px 10px;
            border-radius: 15px;
        }

        .table-responsive {
            width: 100%;
            overflow-x: auto;
            border: 1px solid var(--border);
            border-radius: 10px;
            background: white;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }

        th,
        td {
            border-bottom: 1px solid #f1f5f9;
            padding: 12px;
            text-align: left;
        }

        th {
            background: #f8fafc;
            color: #64748b;
            font-weight: 700;
            cursor: pointer;
            user-select: none;
        }

        tr:hover {
            background-color: #f8fafc;
            cursor: pointer;
        }

        .pagination {
            display: flex;
            gap: 5px;
            margin-top: 12px;
            justify-content: center;
        }

        .page-btn {
            padding: 6px 12px;
            border: 1px solid var(--border);
            background: white;
            cursor: pointer;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 800;
            color: #475569;
        }

        .page-btn.active {
            background: var(--primary);
            color: white;
        }

        .input-container {
            padding: 14px 20px;
            border-top: 1px solid #cbd5e1;
            display: flex;
            gap: 10px;
            background: white;
            align-items: center;
        }

        textarea {
            flex: 1;
            height: 48px;
            padding: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            resize: none;
            font-size: 13.5px;
            outline: none;
            font-family: inherit;
        }

        #sendBtn {
            background: var(--primary);
            color: white;
            border: none;
            width: 50px;
            height: 48px;
            border-radius: 12px;
            cursor: pointer;
            font-size: 1.25rem;
        }

        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.7);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 4000;
            backdrop-filter: blur(4px);
        }

        .modal-card {
            background: white;
            width: 95%;
            max-width: 650px;
            max-height: 88vh;
            border-radius: 20px;
            overflow-y: auto;
            padding: 30px;
            position: relative;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }

        .form-group {
            margin-bottom: 14px;
        }

        .form-group label {
            display: block;
            font-size: 11px;
            font-weight: bold;
            color: #475569;
            margin-bottom: 5px;
            text-transform: uppercase;
        }

        .form-control {
            width: 100%;
            padding: 10px;
            border: 1.5px solid #cbd5e1;
            border-radius: 8px;
            font-size: 13px;
            box-sizing: border-box;
            outline: none;
        }

        .chart-div {
            width: 100% !important;
            max-width: 100%;
            height: 400px;
            min-height: 400px;
        }

        .badge-readonly {
            background: #dcfce7;
            color: #15803d;
            border: 1px solid #86efac;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(8px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 768px) {
            #sidebar {
                position: fixed;
                height: 100%;
                margin-left: -300px;
            }

            #sidebar.active {
                margin-left: 0;
            }

            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <div id="sidebar-overlay" onclick="toggleSidebar()"></div>

    <!-- SIDEBAR -->
    <div id="sidebar" class="collapsed">
        <div class="side-header">
            <span style="font-size: 15px;"><i class="fas fa-brain" style="color:var(--secondary);"></i> BI Inteligência</span>
            <i class="fas fa-times" onclick="toggleSidebar()" style="cursor:pointer;"></i>
        </div>
        <div class="side-content">
            <div class="side-section-title">Configurações Gerais:</div>
            <button class="tool-btn" onclick="openAiConfigModal()"><i class="fas fa-robot"></i> Configurar IA (Chave / Modelo)</button>

            <?php if ($enable_custom_db || $enable_csv_upload): ?>
                <div class="side-section-title">Gerenciar Fontes:</div>
                <?php if ($enable_custom_db): ?>
                    <button class="tool-btn" onclick="openCustomDbModal()"><i class="fas fa-database"></i> Conectar Outro Banco MySQL</button>
                <?php endif; ?>
                <?php if ($enable_csv_upload): ?>
                    <button class="tool-btn" onclick="openCsvModal()"><i class="fas fa-file-csv"></i> Carregar Planilha CSV</button>
                <?php endif; ?>
            <?php endif; ?>

            <!-- SEÇÃO DE ATALHOS / AJUDA DINÂMICA -->
            <div id="sidebar-help-container">
                <?php if ($enable_help_panel): ?>
                    <div id="sidebar-help-content">
                        <div class="side-section-title" id="sidebar-help-title">Sugestões de Análise:</div>
                        <div id="sidebar-help-buttons"></div>
                    </div>
                    <div id="sidebar-help-notice" style="display:none; padding: 14px 10px; font-size: 11px; color: #94a3b8; line-height: 1.5; background: rgba(255,255,255,0.03); border-radius: 8px; margin-top: 15px;">
                        <i class="fas fa-info-circle"></i> <em>Nenhuma sugestão contextual disponível no momento.</em>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- MAIN VIEW -->
    <div id="chat-main">
        <div class="top-bar">
            <div class="top-bar-left">
                <button class="toggle-btn" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>

                <div class="active-source-pill">
                    <span class="status-indicator-dot"></span>
                    <span class="pill-title">FONTE ATIVA:</span>
                    <strong id="activeSourceDisplayName">Base Padrão</strong>
                </div>

                <div class="active-ai-pill" onclick="openAiConfigModal()" title="Clique para alterar chave ou modelo do Gemini">
                    <i class="fas fa-microchip"></i>
                    <span id="topAiModelBadge"><?php echo htmlspecialchars($active_gemini_model); ?></span>
                    <span id="topAiKeyBadge" style="font-size:9.5px; background:rgba(30,64,175,0.12); padding:2px 6px; border-radius:8px; font-weight:800;"><?php echo ($ai_key_source === 'custom') ? 'CHAVE PRÓPRIA' : 'SISTEMA'; ?></span>
                </div>

                <div class="db-selector-box">
                    <select id="dbSourceSelect" class="db-select" onchange="onDbSourceChange(this.value)">
                        <?php if ($enable_default_db): ?>
                            <option value="default" <?php echo ($cur_active_source === 'default') ? 'selected' : ''; ?>>Base Padrão (MySQL)</option>
                        <?php endif; ?>
                        <?php if ($enable_custom_db): ?>
                            <option value="custom" <?php echo ($cur_active_source === 'custom') ? 'selected' : ''; ?>>Base Customizada (MySQL)</option>
                        <?php endif; ?>
                        <?php if ($enable_csv_upload): ?>
                            <option value="csv" <?php echo ($cur_active_source === 'csv') ? 'selected' : ''; ?>>Planilha CSV Ativa</option>
                        <?php endif; ?>
                    </select>
                    <button id="btnConfigureSource" class="btn-db-action" title="Configurar fonte selecionada" onclick="onConfigureCurrentSource()"><i class="fas fa-cog"></i></button>
                </div>
            </div>

            <div class="top-bar-right">
                <button class="btn-pdf-export" onclick="openPdfExportModal()"><i class="fas fa-file-pdf"></i> EXPORTAR PAINEL PDF</button>
                <a href="?reset=1" class="btn-reset" title="Reinicia o histórico da conversa e limpa o cache">LIMPAR</a>
                <?php if ($enable_auth): ?>
                    <a href="?logout=1" class="btn-logout" title="Sair do sistema"><i class="fas fa-sign-out-alt"></i> SAIR</a>
                <?php endif; ?>
            </div>
        </div>

        <div id="chat-log"></div>

        <div class="input-container">
            <textarea id="userInput" placeholder="O que deseja analisar agora?"></textarea>
            <button id="sendBtn"><i class="fas fa-paper-plane"></i></button>
        </div>
    </div>

    <!-- MODAL: ESCOLHA DA ORIENTAÇÃO DO PDF (SLIDE DECK OU RELATÓRIO) -->
    <div id="modal-pdf-choice" class="modal-overlay" onclick="this.style.display='none'">
        <div class="modal-card" onclick="event.stopPropagation()" style="max-width:560px;">
            <h3 style="margin-top:0; color:var(--primary); font-weight:800;"><i class="fas fa-file-pdf"></i> Exportar Painel em PDF</h3>
            <p style="font-size:13px; color:#64748b; margin-bottom:20px;">
                Escolha a diagramação desejada para o documento:
            </p>

            <div style="display:flex; flex-direction:column; gap:12px; margin-bottom:25px;">
                <!-- Opção 1: Paisagem / Slides -->
                <div onclick="executePdfExport('landscape')" style="border:2px solid #e2e8f0; border-radius:14px; padding:15px; cursor:pointer; display:flex; align-items:center; gap:15px; transition:0.2s;" onmouseover="this.style.borderColor='var(--secondary)';this.style.background='#f8fafc';" onmouseout="this.style.borderColor='#e2e8f0';this.style.background='white';">
                    <div style="background:#eff6ff; color:var(--primary); font-size:24px; width:50px; height:50px; border-radius:12px; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                        <i class="fas fa-desktop"></i>
                    </div>
                    <div>
                        <div style="font-size:14px; font-weight:800; color:#1e293b; margin-bottom:3px;">
                            Paisagem • Modo Apresentação (Slides)
                        </div>
                        <div style="font-size:12px; color:#64748b; line-height:1.4;">
                            Cada pergunta e resultado preenchem 100% de uma página horizontal A4, como slides executivos prontos para reunião.
                        </div>
                    </div>
                </div>

                <!-- Opção 2: Retrato / Relatório -->
                <div onclick="executePdfExport('portrait')" style="border:2px solid #e2e8f0; border-radius:14px; padding:15px; cursor:pointer; display:flex; align-items:center; gap:15px; transition:0.2s;" onmouseover="this.style.borderColor='var(--secondary)';this.style.background='#f8fafc';" onmouseout="this.style.borderColor='#e2e8f0';this.style.background='white';">
                    <div style="background:#ecfdf5; color:var(--success); font-size:24px; width:50px; height:50px; border-radius:12px; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <div>
                        <div style="font-size:14px; font-weight:800; color:#1e293b; margin-bottom:3px;">
                            Retrato • Modo Relatório Formal
                        </div>
                        <div style="font-size:12px; color:#64748b; line-height:1.4;">
                            Documento A4 vertical contínuo com diagramação limpa e sem cortes de margem, ideal para impressão em papel e leitura técnica.
                        </div>
                    </div>
                </div>
            </div>

            <button onclick="document.getElementById('modal-pdf-choice').style.display='none'" style="width:100%; padding:12px; border:none; border-radius:10px; background:#f1f5f9; font-weight:bold; color:#64748b; cursor:pointer;">CANCELAR</button>
        </div>
    </div>

    <!-- MODAL: FICHA DETALHADA -->
    <div id="modal-overlay" class="modal-overlay" onclick="this.style.display='none'">
        <div class="modal-card" onclick="event.stopPropagation()">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <h3 style="margin:0; color:var(--primary); font-weight:800;">Ficha Detalhada</h3>
                <button id="btn-modal-copy" style="background:var(--primary); color:white; border:none; padding:8px 18px; border-radius:10px; font-size:12px; font-weight:bold; cursor:pointer;">COPIAR DADOS</button>
            </div>
            <div id="m-body" style="margin-bottom:20px;"></div>
            <button onclick="document.getElementById('modal-overlay').style.display='none'" style="width:100%; padding:12px; border:none; border-radius:12px; background:#f1f5f9; font-weight:bold; color:#64748b; cursor:pointer;">FECHAR</button>
        </div>
    </div>

    <!-- MODAL: CONFIGURAÇÃO DE IA (CHAVE GEMINI E MODELO) -->
    <div id="modal-ai-config" class="modal-overlay" onclick="this.style.display='none'">
        <div class="modal-card" onclick="event.stopPropagation()" style="max-width:550px;">
            <h3 style="margin-top:0; color:var(--primary); font-weight:800;"><i class="fas fa-robot"></i> Configurar IA (Google Gemini)</h3>
            <p style="font-size:12px; color:#64748b; margin-bottom:18px;">
                Gerencie o modelo em execução e a chave de autenticação para as consultas analíticas.
            </p>

            <div class="form-group">
                <label>Modelo do Gemini:</label>
                <select id="ai_selected_model" class="form-control" style="font-weight:bold; color:#1e293b;">
                    <?php foreach ($gemini_available_models as $mod): ?>
                        <option value="<?php echo htmlspecialchars($mod); ?>" <?php echo ($mod === $active_gemini_model) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($mod); ?><?php echo ($mod === $gemini_model) ? ' (Padrão do Script)' : ''; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" style="margin-top:20px;">
                <label>Chave da API Gemini:</label>
                <div style="background:#f8fafc; border:1px solid #cbd5e1; border-radius:10px; padding:12px; margin-bottom:10px;">
                    <label style="display:flex; align-items:center; gap:8px; cursor:pointer; margin:0; text-transform:none; font-size:13px; font-weight:bold; color:#1e293b;">
                        <input type="radio" name="ai_key_option" value="default" <?php echo ($ai_key_source === 'default') ? 'checked' : ''; ?> onchange="toggleAiKeyInput(this.value)">
                        Utilizar Chave Pré-configurada do Sistema (Privada)
                    </label>
                    <div style="font-size:11px; color:#64748b; margin-left:24px; margin-top:4px;">
                        A chave configurada internamente no servidor é utilizada com segurança sem ser exibida na tela.
                    </div>
                </div>

                <div style="background:#f8fafc; border:1px solid #cbd5e1; border-radius:10px; padding:12px;">
                    <label style="display:flex; align-items:center; gap:8px; cursor:pointer; margin:0; text-transform:none; font-size:13px; font-weight:bold; color:#1e293b;">
                        <input type="radio" name="ai_key_option" value="custom" <?php echo ($ai_key_source === 'custom') ? 'checked' : ''; ?> onchange="toggleAiKeyInput(this.value)">
                        Informar Chave Pessoal (Apenas para esta Sessão)
                    </label>
                    <div style="font-size:11px; color:#64748b; margin-left:24px; margin-top:4px; margin-bottom:8px;">
                        Útil para utilizar sua própria conta sem alterar a configuração global do servidor.
                    </div>

                    <div id="custom_key_wrapper" style="<?php echo ($ai_key_source === 'custom') ? 'display:block;' : 'display:none;'; ?> margin-left:24px;">
                        <input type="password" id="ai_custom_key_input" class="form-control" placeholder="Cole sua chave API Gemini aqui (AIzaSy...)" value="<?php echo ($ai_key_source === 'custom' && isset($_SESSION['ai_config']['custom_key'])) ? htmlspecialchars($_SESSION['ai_config']['custom_key']) : ''; ?>">
                    </div>
                </div>
            </div>

            <div id="ai_config_status" style="display:none; margin:15px 0; padding:10px; border-radius:8px; font-size:12px; font-weight:bold;"></div>

            <div style="display:flex; gap:10px; margin-top:20px;">
                <button onclick="saveAiConfig()" style="flex:2; padding:12px; background:var(--primary); color:white; border:none; border-radius:10px; font-weight:bold; cursor:pointer;"><i class="fas fa-save"></i> SALVAR CONFIGURAÇÕES</button>
                <button onclick="document.getElementById('modal-ai-config').style.display='none'" style="flex:1; padding:12px; background:#f1f5f9; border:none; border-radius:10px; font-weight:bold; color:#64748b; cursor:pointer;">CANCELAR</button>
            </div>
        </div>
    </div>

    <!-- MODAL: AVISOS DO SISTEMA PADRONIZADOS -->
    <div id="modal-notice" class="modal-overlay" onclick="this.style.display='none'">
        <div class="modal-card" onclick="event.stopPropagation()" style="max-width:550px;">
            <h3 id="notice-title" style="margin-top:0; color:var(--primary); font-weight:800;"><i class="fas fa-info-circle"></i> Informação</h3>
            <div id="notice-body" style="font-size:13.5px; color:#334155; line-height:1.6; margin-bottom:22px;"></div>
            <button onclick="document.getElementById('modal-notice').style.display='none'" style="width:100%; padding:12px; border:none; border-radius:12px; background:var(--primary); font-weight:bold; color:white; cursor:pointer;">ENTENDIDO</button>
        </div>
    </div>

    <!-- MODAL: CONFIRMAÇÃO DE FONTE JÁ CONFIGURADA (MANTER OU ALTERAR) -->
    <div id="modal-switch-confirm" class="modal-overlay" onclick="cancelSourceSwitch()">
        <div class="modal-card" onclick="event.stopPropagation()" style="max-width:520px;">
            <h3 id="switch-confirm-title" style="margin-top:0; color:var(--primary); font-weight:800;"><i class="fas fa-layer-group"></i> Fonte Já Ativa</h3>
            <div id="switch-confirm-body" style="font-size:13.5px; color:#334155; line-height:1.6; margin-bottom:24px;"></div>
            <div style="display:flex; gap:10px;">
                <button id="btnKeepSource" style="flex:1; padding:12px; background:var(--primary); color:white; border:none; border-radius:10px; font-weight:bold; cursor:pointer;"><i class="fas fa-check"></i> MANTER ATUAL</button>
                <button id="btnChangeSource" style="flex:1; padding:12px; background:#f1f5f9; color:#1e293b; border:1px solid #cbd5e1; border-radius:10px; font-weight:bold; cursor:pointer;"><i class="fas fa-sync-alt"></i> RECONFIGURAR</button>
            </div>
        </div>
    </div>

    <!-- MODAL: CONECTAR BASE CUSTOMIZADA COM READ-ONLY -->
    <div id="modal-custom-db" class="modal-overlay" onclick="cancelCustomDbModal()">
        <div class="modal-card" onclick="event.stopPropagation()">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                <h3 style="margin:0; color:var(--primary); font-weight:800;"><i class="fas fa-database"></i> Conectar Outro Banco MySQL</h3>
                <span class="badge-readonly"><i class="fas fa-lock"></i> Somente Leitura</span>
            </div>

            <p style="font-size:12px; color:#64748b; margin-bottom:18px;">
                Informe as credenciais de acesso da base. A conexão será blindada contra alterações acidentais.
            </p>

            <div style="display:flex; gap:10px;">
                <div class="form-group" style="flex:3;">
                    <label>Servidor / Host:</label>
                    <input type="text" id="cust_host" class="form-control" placeholder="Ex: 127.0.0.1 ou db.empresa.com">
                </div>
                <div class="form-group" style="flex:1;">
                    <label>Porta:</label>
                    <input type="number" id="cust_port" class="form-control" value="3306">
                </div>
            </div>

            <div style="display:flex; gap:10px;">
                <div class="form-group" style="flex:1;">
                    <label>Usuário:</label>
                    <input type="text" id="cust_user" class="form-control" placeholder="Ex: usr_readonly">
                </div>
                <div class="form-group" style="flex:1;">
                    <label>Senha:</label>
                    <input type="password" id="cust_pass" class="form-control" placeholder="Senha do banco">
                </div>
            </div>

            <div class="form-group">
                <label>Nome do Banco de Dados (Schema):</label>
                <input type="text" id="cust_name" class="form-control" placeholder="Ex: erp_analitico">
            </div>

            <div id="cust_status_box" style="display:none; margin:15px 0; padding:12px; border-radius:10px; font-size:12px; font-weight:bold;"></div>

            <div style="display:flex; gap:10px; margin-top:20px;">
                <button id="btnSaveCustomDb" onclick="saveCustomDb()" style="flex:2; padding:12px; background:var(--primary); color:white; border:none; border-radius:10px; font-weight:bold; cursor:pointer;"><i class="fas fa-plug"></i> CONECTAR</button>
                <button onclick="cancelCustomDbModal()" style="flex:1; padding:12px; background:#f1f5f9; border:none; border-radius:10px; font-weight:bold; color:#64748b; cursor:pointer;">CANCELAR</button>
            </div>
        </div>
    </div>

    <!-- MODAL: UPLOAD CSV -->
    <div id="modal-csv" class="modal-overlay" onclick="cancelCsvModal()">
        <div class="modal-card" onclick="event.stopPropagation()">
            <h3 style="margin-top:0; color:var(--primary); font-weight:800;"><i class="fas fa-file-csv"></i> Análise de Planilha CSV</h3>
            <p style="font-size:12px; color:#64748b; margin-bottom:15px;">Envie uma planilha (Google Forms, Excel). Os dados serão estruturados automaticamente em uma base relacional instantânea.</p>

            <input type="file" id="csvFileInput" accept=".csv" class="form-control" style="padding:15px; border:2px dashed #cbd5e1; background:#f8fafc; cursor:pointer;">

            <div id="csvProgress" style="display:none; margin-top:15px; font-size:12px; color:var(--primary); font-weight:bold;"><i class="fas fa-circle-notch fa-spin"></i> Processando e gerando dashboard inicial...</div>

            <div style="display:flex; gap:10px; margin-top:20px;">
                <button onclick="uploadCsvFile()" style="flex:2; padding:12px; background:var(--success); color:white; border:none; border-radius:10px; font-weight:bold; cursor:pointer;"><i class="fas fa-upload"></i> CARREGAR E ANALISAR CSV</button>
                <button onclick="cancelCsvModal()" style="flex:1; padding:12px; background:#f1f5f9; border:none; border-radius:10px; font-weight:bold; color:#64748b; cursor:pointer;">FECHAR</button>
            </div>
        </div>
    </div>

    <script>
        var log = document.getElementById('chat-log'),
            input = document.getElementById('userInput'),
            btn = document.getElementById('sendBtn'),
            modalOverlay = document.getElementById('modal-overlay');
        var FILE_ROOT = '<?php echo $base_file_url; ?>',
            biRegistry = {},
            echartInstances = [];

        var STATE = {
            activeSource: '<?php echo $cur_active_source; ?>',
            previousSource: '<?php echo $cur_active_source; ?>',
            hasCustomDb: <?php echo $has_custom_db ? 'true' : 'false'; ?>,
            customDbInfo: '<?php echo addslashes($custom_db_info); ?>',
            customDbExamples: <?php echo json_encode($custom_db_examples); ?>,
            hasCsv: <?php echo $has_csv ? 'true' : 'false'; ?>,
            csvInfo: '<?php echo addslashes($csv_info); ?>',
            csvExamples: <?php echo json_encode($csv_examples); ?>,
            defaultDbInfo: '<?php echo addslashes($default_db_info); ?>',
            defaultDbExamples: <?php echo json_encode($help_examples); ?>,
            enableDefaultDb: <?php echo $enable_default_db ? 'true' : 'false'; ?>,
            enableHelpPanel: <?php echo $enable_help_panel ? 'true' : 'false'; ?>,
            activeAiModel: '<?php echo addslashes($active_gemini_model); ?>',
            aiKeySource: '<?php echo addslashes($ai_key_source); ?>',
            pdfOrientation: '<?php echo $normalized_pdf_orientation; ?>'
        };

        function formatDateValue(val) {
            if (val === null || val === undefined || typeof val === 'number' || typeof val === 'boolean') return val;
            var s = String(val).trim();
            var mDate = s.match(/^(\d{4})-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/);
            if (mDate) return mDate[3] + '/' + mDate[2] + '/' + mDate[1];
            var mDateTime = s.match(/^(\d{4})-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])[ T](\d{2}):(\d{2})(?::(\d{2}))?/);
            if (mDateTime) {
                var y = mDateTime[1],
                    m = mDateTime[2],
                    d = mDateTime[3],
                    hh = mDateTime[4],
                    mm = mDateTime[5],
                    ss = mDateTime[6];
                if (hh === '00' && mm === '00' && (!ss || ss === '00')) return d + '/' + m + '/' + y;
                return d + '/' + m + '/' + y + ' ' + hh + ':' + mm + (ss ? ':' + ss : '');
            }
            return val;
        }

        function getFieldLabel(key, customLabels) {
            if (!key) return '';
            if (customLabels && typeof customLabels === 'object') {
                if (customLabels[key]) return customLabels[key];
                var lowerK = String(key).toLowerCase().trim();
                for (var k in customLabels) {
                    if (k.toLowerCase().trim() === lowerK) return customLabels[k];
                }
            }
            var text = String(key).replace(/[_-]+/g, ' ').replace(/([a-z0-9])([A-Z])/g, '$1 $2').trim();
            var prepositions = ['de', 'da', 'do', 'das', 'dos', 'e', 'em', 'para', 'por', 'com', 'no', 'na', 'nos', 'nas'];
            return text.split(/\s+/).map(function(w, i) {
                var lw = w.toLowerCase();
                if (i > 0 && prepositions.indexOf(lw) !== -1) return lw;
                return lw.charAt(0).toUpperCase() + lw.slice(1);
            }).join(' ');
        }

        function toggleSidebar() {
            var s = document.getElementById('sidebar'),
                o = document.getElementById('sidebar-overlay');
            if (window.innerWidth <= 768) {
                var act = s.classList.toggle('active');
                o.style.display = act ? 'block' : 'none';
                s.classList.remove('collapsed');
            } else {
                s.classList.toggle('collapsed');
                setTimeout(function() {
                    echartInstances.forEach(function(i) {
                        i.resize();
                    });
                }, 350);
            }
        }

        // REMOVE BLOCO INTEGRADO DE PERGUNTA E RESPOSTA DO PAINEL
        function removeTurnBlock(turnId) {
            var block = document.getElementById(turnId);
            if (block) {
                block.style.opacity = '0';
                block.style.transform = 'scale(0.98)';
                setTimeout(function() {
                    if (block.parentNode) {
                        block.parentNode.removeChild(block);
                    }
                }, 250);
            }
        }

        // RENDERIZADOR DINÂMICO DOS BOTÕES DE ATALHOS / PERGUNTAS SUGERIDAS
        function renderSidebarHelpButtons(examplesList, sourceLabel) {
            var container = document.getElementById('sidebar-help-buttons');
            var titleEl = document.getElementById('sidebar-help-title');
            var helpContent = document.getElementById('sidebar-help-content');
            var helpNotice = document.getElementById('sidebar-help-notice');

            if (!container || !titleEl || !helpContent) return;

            if (examplesList && examplesList.length > 0) {
                titleEl.innerText = 'Sugestões (' + sourceLabel + '):';
                var html = '';
                for (var i = 0; i < examplesList.length; i++) {
                    var ex = String(examplesList[i]).replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/"/g, '&quot;');
                    html += '<button class="example-btn" onclick="handleHelp(\'' + ex + '\')"><i class="fas fa-magic"></i> ' + examplesList[i] + '</button>';
                }
                container.innerHTML = html;
                helpContent.style.display = 'block';
                if (helpNotice) helpNotice.style.display = 'none';
            } else {
                container.innerHTML = '';
                helpContent.style.display = 'none';
                if (helpNotice) helpNotice.style.display = 'block';
            }
        }

        // ATUALIZAÇÃO VISUAL COMPLETA DA FONTE ATIVA E SUAS SUGESTÕES
        function updateActiveSourceUI(source) {
            STATE.activeSource = source;
            STATE.previousSource = source;
            document.getElementById('dbSourceSelect').value = source;

            var displayName = 'Base Padrão';
            var placeholderText = 'O que deseja analisar agora?';

            if (source === 'default') {
                displayName = 'Base Padrão: ' + STATE.defaultDbInfo;
                placeholderText = 'Consultando [' + STATE.defaultDbInfo + ']... Faça uma pergunta';
                renderSidebarHelpButtons(STATE.defaultDbExamples, 'Base Padrão');
            } else if (source === 'custom') {
                displayName = STATE.hasCustomDb ? 'Custom: ' + STATE.customDbInfo : 'Base Customizada (Pendente)';
                placeholderText = 'Consultando [' + (STATE.hasCustomDb ? STATE.customDbInfo : 'Custom') + ']... Faça uma pergunta';
                renderSidebarHelpButtons(STATE.customDbExamples, 'Custom');
            } else if (source === 'csv') {
                displayName = STATE.hasCsv ? 'CSV: ' + STATE.csvInfo : 'Planilha CSV (Pendente)';
                placeholderText = 'Consultando [' + (STATE.hasCsv ? STATE.csvInfo : 'Planilha') + ']... Faça uma pergunta';
                renderSidebarHelpButtons(STATE.csvExamples, 'CSV');
            }

            document.getElementById('activeSourceDisplayName').innerText = displayName;
            input.placeholder = placeholderText;

            fetch('?action=set_active_source', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    source: source
                })
            });
        }

        function openAiConfigModal() {
            document.getElementById('ai_config_status').style.display = 'none';
            document.getElementById('modal-ai-config').style.display = 'flex';
        }

        function toggleAiKeyInput(val) {
            var wrapper = document.getElementById('custom_key_wrapper');
            if (val === 'custom') {
                wrapper.style.display = 'block';
                document.getElementById('ai_custom_key_input').focus();
            } else {
                wrapper.style.display = 'none';
            }
        }

        async function saveAiConfig() {
            var model = document.getElementById('ai_selected_model').value;
            var keyTypeRadios = document.getElementsByName('ai_key_option');
            var keyType = 'default';
            for (var i = 0; i < keyTypeRadios.length; i++) {
                if (keyTypeRadios[i].checked) keyType = keyTypeRadios[i].value;
            }
            var customKey = document.getElementById('ai_custom_key_input').value.trim();
            var statusBox = document.getElementById('ai_config_status');

            if (keyType === 'custom' && !customKey) {
                statusBox.style.display = 'block';
                statusBox.style.background = '#fee2e2';
                statusBox.style.color = '#dc2626';
                statusBox.innerHTML = 'Por favor, informe a sua chave pessoal da API Gemini.';
                return;
            }

            try {
                var resp = await fetch('?action=save_ai_config', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        model: model,
                        key_type: keyType,
                        custom_key: customKey
                    })
                });
                var res = await resp.json();
                if (res.status === 'success') {
                    STATE.activeAiModel = res.model;
                    STATE.aiKeySource = res.key_type;

                    document.getElementById('topAiModelBadge').innerText = res.model;
                    document.getElementById('topAiKeyBadge').innerText = (res.key_type === 'custom') ? 'CHAVE PRÓPRIA' : 'SISTEMA';

                    statusBox.style.display = 'block';
                    statusBox.style.background = '#dcfce7';
                    statusBox.style.color = '#15803d';
                    statusBox.innerHTML = '<i class="fas fa-check-circle"></i> ' + res.message;

                    setTimeout(function() {
                        document.getElementById('modal-ai-config').style.display = 'none';
                    }, 1200);
                } else {
                    statusBox.style.display = 'block';
                    statusBox.style.background = '#fee2e2';
                    statusBox.style.color = '#dc2626';
                    statusBox.innerHTML = 'Erro: ' + res.message;
                }
            } catch (e) {
                statusBox.style.display = 'block';
                statusBox.style.background = '#fee2e2';
                statusBox.style.color = '#dc2626';
                statusBox.innerHTML = 'Erro ao salvar configuração da IA.';
            }
        }

        window.addEventListener('load', function() {
            updateActiveSourceUI(STATE.activeSource);

            if (STATE.enableDefaultDb && STATE.enableHelpPanel) {
                var firstExample = document.querySelector('.example-btn');
                if (firstExample && log.children.length === 0) {
                    handleHelp(firstExample.innerText.trim());
                }
            } else if (!STATE.enableDefaultDb && log.children.length === 0) {
                var introBox = document.createElement('div');
                introBox.className = 'chat-turn-block';
                introBox.innerHTML = '<div class="card" style="border-left: 5px solid var(--secondary); max-width: 800px;">' +
                    '<h3 style="margin-top:0; color:var(--primary); font-weight:800;"><i class="fas fa-compass"></i> Bem-vindo ao Assistente Analítico</h3>' +
                    '<p style="font-size:13.5px; color:#334155; line-height:1.6;">Nenhuma base de dados padrão está ativa no momento. Para iniciar sua análise:</p>' +
                    '<ul style="font-size:13.5px; color:#334155; line-height:1.8;">' +
                    '<?php if ($enable_custom_db): ?><li>Clique em <strong>Conectar Outro Banco MySQL</strong> no menu lateral ou no topo.</li><?php endif; ?>' +
                    '<?php if ($enable_csv_upload): ?><li>Clique em <strong>Carregar Planilha CSV</strong> para importar seus formulários ou tabelas.</li><?php endif; ?>' +
                    '</ul>' +
                    '<p style="margin-bottom:0; color:#64748b; font-size:12px;">Após conectar sua fonte, um dashboard completo e perguntas analíticas serão geradas automaticamente.</p>' +
                    '</div>';
                log.appendChild(introBox);
            }
        });

        window.addEventListener('resize', function() {
            echartInstances.forEach(function(i) {
                i.resize();
            });
        });
        var norm = function(s) {
            return String(s).normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase();
        };

        function handleHelp(t) {
            if (window.innerWidth <= 768) {
                document.getElementById('sidebar').classList.remove('active');
                document.getElementById('sidebar-overlay').style.display = 'none';
            }
            input.value = t;
            input.focus();
            if (t.indexOf(':') === -1) send();
        }

        function showNoticeModal(title, message) {
            document.getElementById('notice-title').innerHTML = '<i class="fas fa-info-circle"></i> ' + title;
            document.getElementById('notice-body').innerHTML = message;
            document.getElementById('modal-notice').style.display = 'flex';
        }

        function openCustomDbModal() {
            document.getElementById('modal-custom-db').style.display = 'flex';
        }

        function openCsvModal() {
            document.getElementById('modal-csv').style.display = 'flex';
        }

        function openPdfExportModal() {
            if (log.children.length === 0) {
                showNoticeModal('Exportação Indisponível', 'Nenhuma análise gerada no painel para exportar.');
                return;
            }
            document.getElementById('modal-pdf-choice').style.display = 'flex';
        }

        function cancelCustomDbModal() {
            document.getElementById('modal-custom-db').style.display = 'none';
            document.getElementById('dbSourceSelect').value = STATE.activeSource;
        }

        function cancelCsvModal() {
            document.getElementById('modal-csv').style.display = 'none';
            document.getElementById('dbSourceSelect').value = STATE.activeSource;
        }

        function cancelSourceSwitch() {
            document.getElementById('modal-switch-confirm').style.display = 'none';
            document.getElementById('dbSourceSelect').value = STATE.activeSource;
        }

        function onDbSourceChange(val) {
            if (val === 'default') {
                updateActiveSourceUI('default');
            } else if (val === 'custom') {
                if (STATE.hasCustomDb) {
                    document.getElementById('switch-confirm-title').innerHTML = '<i class="fas fa-database"></i> Base Customizada Já Conectada';
                    document.getElementById('switch-confirm-body').innerHTML = 'Você já possui a base <strong>' + STATE.customDbInfo + '</strong> conectada e pronta para consultas.<br><br>Deseja continuar utilizando esta base ou deseja reconfigurar uma nova conexão?';

                    document.getElementById('btnKeepSource').onclick = function() {
                        document.getElementById('modal-switch-confirm').style.display = 'none';
                        updateActiveSourceUI('custom');
                    };
                    document.getElementById('btnChangeSource').onclick = function() {
                        document.getElementById('modal-switch-confirm').style.display = 'none';
                        openCustomDbModal();
                    };
                    document.getElementById('modal-switch-confirm').style.display = 'flex';
                } else {
                    openCustomDbModal();
                }
            } else if (val === 'csv') {
                if (STATE.hasCsv) {
                    document.getElementById('switch-confirm-title').innerHTML = '<i class="fas fa-file-csv"></i> Planilha CSV Já Carregada';
                    document.getElementById('switch-confirm-body').innerHTML = 'A planilha <strong>' + STATE.csvInfo + '</strong> já está carregada e ativa.<br><br>Deseja continuar analisando esta planilha ou deseja carregar um novo arquivo CSV?';

                    document.getElementById('btnKeepSource').onclick = function() {
                        document.getElementById('modal-switch-confirm').style.display = 'none';
                        updateActiveSourceUI('csv');
                    };
                    document.getElementById('btnChangeSource').onclick = function() {
                        document.getElementById('modal-switch-confirm').style.display = 'none';
                        openCsvModal();
                    };
                    document.getElementById('modal-switch-confirm').style.display = 'flex';
                } else {
                    openCsvModal();
                }
            }
        }

        function onConfigureCurrentSource() {
            var current = document.getElementById('dbSourceSelect').value;
            if (current === 'custom') {
                openCustomDbModal();
            } else if (current === 'csv') {
                openCsvModal();
            } else {
                showNoticeModal(
                    'Base Padrão Ativa',
                    'A <strong>Base Padrão</strong> (' + STATE.defaultDbInfo + ') utiliza as credenciais nativas configuradas no script pelo administrador.'
                );
            }
        }

        async function saveCustomDb() {
            var host = document.getElementById('cust_host').value.trim();
            var port = document.getElementById('cust_port').value.trim();
            var user = document.getElementById('cust_user').value.trim();
            var pass = document.getElementById('cust_pass').value;
            var name = document.getElementById('cust_name').value.trim();
            var statusBox = document.getElementById('cust_status_box');
            var saveBtn = document.getElementById('btnSaveCustomDb');

            if (!host || !user || !name) {
                showNoticeModal('Campos Obrigatórios', 'Por favor, preencha o Servidor, Usuário e Nome do Banco de Dados.');
                return;
            }

            saveBtn.disabled = true;
            statusBox.style.display = 'block';
            statusBox.style.background = '#eff6ff';
            statusBox.style.color = '#1e40af';
            statusBox.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Conectando, gerando dashboard e ativando modo Read-Only...';

            try {
                var resp = await fetch('?action=save_custom_db', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        host: host,
                        port: port,
                        user: user,
                        pass: pass,
                        name: name
                    })
                });
                var res = await resp.json();

                if (res.status === 'success') {
                    statusBox.style.background = '#dcfce7';
                    statusBox.style.color = '#15803d';
                    statusBox.innerHTML = '<i class="fas fa-check-circle"></i> ' + res.message;

                    STATE.hasCustomDb = true;
                    STATE.customDbInfo = res.db_name + ' (' + res.db_host + ')';
                    STATE.customDbExamples = res.examples || [];

                    setTimeout(function() {
                        document.getElementById('modal-custom-db').style.display = 'none';
                        statusBox.style.display = 'none';
                        saveBtn.disabled = false;
                        updateActiveSourceUI('custom');

                        // Executa automaticamente o Dashboard inicial da base conectada
                        if (STATE.customDbExamples.length > 0) {
                            handleHelp(STATE.customDbExamples[0]);
                        }
                    }, 1200);
                } else {
                    statusBox.style.background = '#fee2e2';
                    statusBox.style.color = '#dc2626';
                    statusBox.innerHTML = '<i class="fas fa-times-circle"></i> Erro: ' + res.message;
                    saveBtn.disabled = false;
                }
            } catch (e) {
                statusBox.style.background = '#fee2e2';
                statusBox.style.color = '#dc2626';
                statusBox.innerHTML = '<i class="fas fa-times-circle"></i> Erro de conexão com o servidor PHP.';
                saveBtn.disabled = false;
            }
        }

        async function uploadCsvFile() {
            var fileInput = document.getElementById('csvFileInput');
            if (!fileInput.files || !fileInput.files[0]) {
                showNoticeModal('Arquivo Não Selecionado', 'Selecione um arquivo <strong>.csv</strong> primeiro para fazer o upload.');
                return;
            }
            var formData = new FormData();
            formData.append('csv_file', fileInput.files[0]);

            document.getElementById('csvProgress').style.display = 'block';
            try {
                var resp = await fetch('?action=upload_csv', {
                    method: 'POST',
                    body: formData
                });
                var res = await resp.json();
                document.getElementById('csvProgress').style.display = 'none';

                document.getElementById('modal-csv').style.display = 'none';
                fileInput.value = '';

                if (res.status === 'success') {
                    STATE.hasCsv = true;
                    STATE.csvInfo = res.data.filename + ' (' + res.data.rows + ' registros)';
                    STATE.csvExamples = res.examples || [];
                    updateActiveSourceUI('csv');

                    var modalTitle = 'Planilha Carregada com Sucesso';
                    var fullMessage = '<div style="margin-bottom:12px;">' + res.message + '</div>';
                    fullMessage += '<p style="font-size:12px; color:#64748b; margin-top:8px;"><i class="fas fa-magic"></i> <strong>Dashboard Inicial</strong> gerado automaticamente com base na estrutura da planilha.</p>';

                    if (res.notice && res.notice !== '') {
                        modalTitle = 'Planilha Carregada (Modo Nativo PHP)';
                        fullMessage += '<div style="background:#fffbeb; border:1px solid #fef3c7; color:#92400e; padding:12px; border-radius:10px; font-size:12.5px; text-align:left; line-height:1.6; margin-top:10px;">' + res.notice + '</div>';
                    }

                    showNoticeModal(modalTitle, fullMessage);

                    // Executa automaticamente o Dashboard inicial da planilha
                    if (STATE.csvExamples.length > 0) {
                        setTimeout(function() {
                            handleHelp(STATE.csvExamples[0]);
                        }, 400);
                    }
                } else {
                    showNoticeModal('Erro no Arquivo', 'Falha ao processar o CSV: ' + res.message);
                }
            } catch (e) {
                document.getElementById('csvProgress').style.display = 'none';
                document.getElementById('modal-csv').style.display = 'none';
                showNoticeModal('Erro de Conexão', 'Não foi possível enviar o arquivo CSV ao servidor.');
            }
        }

        // INICIA A EXPORTAÇÃO APÓS A ESCOLHA NO MODAL
        function executePdfExport(chosenOrientation) {
            document.getElementById('modal-pdf-choice').style.display = 'none';
            exportFullDashboardPDF(chosenOrientation);
        }

        // EXPORTAÇÃO EM PDF: RETRATO E PAISAGEM SEM CORTES DE MARGEM
        async function exportFullDashboardPDF(orientation) {
            var turnBlocks = log.querySelectorAll('.chat-turn-block');
            var turns = [];

            // Captura apenas os blocos que NÃO foram removidos pelo usuário
            turnBlocks.forEach(function(block) {
                var userTextEl = block.querySelector('.user-prompt-text');
                var sourceTagEl = block.querySelector('.source-tag');
                var promptText = userTextEl ? userTextEl.innerText.trim() : "Consulta Analítica";
                var sourceText = sourceTagEl ? sourceTagEl.innerText.trim() : STATE.activeSource.toUpperCase();

                var cards = block.querySelectorAll('.card');
                cards.forEach(function(card) {
                    if (card.querySelector('.fa-circle-notch')) return;
                    turns.push({
                        prompt: promptText,
                        source: sourceText,
                        card: card
                    });
                });
            });

            if (turns.length === 0) {
                showNoticeModal('Exportação Indisponível', 'Nenhuma análise gerada no painel para exportar.');
                return;
            }

            var isLandscape = (orientation === 'landscape');
            var mainEl = document.getElementById('chat-main');
            var sidebarEl = document.getElementById('sidebar');
            var savedScrollY = window.pageYOffset || document.documentElement.scrollTop;

            var wrapper = document.createElement('div');
            wrapper.id = 'pdf-export-wrapper';
            wrapper.style.width = isLandscape ? '297mm' : '210mm';
            wrapper.style.maxWidth = isLandscape ? '297mm' : '210mm';
            wrapper.style.margin = '0 auto';
            wrapper.style.background = '#ffffff';
            wrapper.style.color = '#0f172a';
            wrapper.style.boxSizing = 'border-box';
            wrapper.style.padding = isLandscape ? '0' : '12mm 14mm';
            wrapper.style.overflow = 'hidden';

            if (isLandscape) {
                // MODO PAISAGEM: APRESENTAÇÃO DE SLIDES
                for (var i = 0; i < turns.length; i++) {
                    var item = turns[i];
                    var card = item.card;
                    var cardTitle = card.querySelector('.card-title') ? card.querySelector('.card-title').innerText.trim() : '';

                    var slide = document.createElement('div');
                    slide.style.width = '297mm';
                    slide.style.height = '205mm';
                    slide.style.boxSizing = 'border-box';
                    slide.style.padding = '12mm 16mm 10mm 16mm';
                    slide.style.display = 'flex';
                    slide.style.flexDirection = 'column';
                    slide.style.justifyContent = 'space-between';
                    slide.style.background = '#ffffff';
                    slide.style.pageBreakAfter = (i === turns.length - 1) ? 'auto' : 'always';
                    slide.style.breakAfter = (i === turns.length - 1) ? 'auto' : 'page';
                    slide.style.overflow = 'hidden';

                    var headerDiv = document.createElement('div');
                    headerDiv.style.marginBottom = '8px';
                    headerDiv.innerHTML = '<div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1.5px solid #e2e8f0; padding-bottom:5px; margin-bottom:10px;">' +
                        '<span style="font-size:9.5px; font-weight:800; color:#1e40af; text-transform:uppercase; letter-spacing:0.8px;"><i class="fas fa-chart-line"></i> BI Analítico Executivo • Apresentação de Resultados</span>' +
                        '<span style="font-size:9px; font-weight:700; color:#475569; background:#f1f5f9; padding:2px 8px; border-radius:12px; border:1px solid #e2e8f0;">FONTE: ' + item.source + '</span>' +
                        '</div>' +
                        '<div style="font-size:13.5pt; font-weight:800; color:#0f172a; line-height:1.3; border-left:4px solid #3b82f6; padding-left:10px;">' + item.prompt + '</div>';
                    slide.appendChild(headerDiv);

                    var bodyDiv = document.createElement('div');
                    bodyDiv.style.flex = '1';
                    bodyDiv.style.display = 'flex';
                    bodyDiv.style.flexDirection = 'column';
                    bodyDiv.style.justifyContent = 'center';
                    bodyDiv.style.alignItems = 'center';
                    bodyDiv.style.width = '100%';
                    bodyDiv.style.overflow = 'hidden';

                    var canvas = card.querySelector('canvas');
                    var kpiEl = card.querySelector('.kpi-value');
                    var textEl = card.querySelector('.analysis-text');
                    var tableEl = card.querySelector('table');

                    if (canvas) {
                        var img = document.createElement('img');
                        img.src = canvas.toDataURL('image/png');
                        img.style.maxHeight = '112mm';
                        img.style.maxWidth = '95%';
                        img.style.objectFit = 'contain';
                        img.style.display = 'block';
                        img.style.margin = '0 auto';

                        if (cardTitle) {
                            var tDiv = document.createElement('div');
                            tDiv.style.fontSize = '12pt';
                            tDiv.style.fontWeight = 'bold';
                            tDiv.style.color = '#1e293b';
                            tDiv.style.marginBottom = '6px';
                            tDiv.style.textAlign = 'center';
                            tDiv.innerText = cardTitle;
                            bodyDiv.appendChild(tDiv);
                        }
                        bodyDiv.appendChild(img);
                    } else if (kpiEl) {
                        var kpiBox = document.createElement('div');
                        kpiBox.style.textAlign = 'center';
                        kpiBox.style.padding = '20px';
                        kpiBox.innerHTML = '<div style="font-size:4.8rem; font-weight:900; color:#1e40af; line-height:1; letter-spacing:-1px;">' + kpiEl.innerText + '</div>' +
                            '<div style="font-size:1.3rem; font-weight:800; color:#64748b; text-transform:uppercase; margin-top:15px;">' + cardTitle + '</div>';
                        bodyDiv.appendChild(kpiBox);
                    } else if (textEl) {
                        var textBox = document.createElement('div');
                        textBox.style.width = '100%';
                        textBox.style.maxHeight = '115mm';
                        textBox.style.overflow = 'hidden';
                        textBox.style.fontSize = '10.5pt';
                        textBox.style.lineHeight = '1.55';
                        textBox.style.color = '#1e293b';
                        textBox.style.background = '#f8fafc';
                        textBox.style.border = '1px solid #e2e8f0';
                        textBox.style.borderRadius = '12px';
                        textBox.style.padding = '15px 20px';
                        textBox.style.boxSizing = 'border-box';
                        textBox.innerHTML = (cardTitle ? '<h4 style="margin:0 0 10px 0; color:#1e40af; font-size:12pt;">' + cardTitle + '</h4>' : '') + textEl.innerHTML;
                        bodyDiv.appendChild(textBox);
                    } else if (tableEl) {
                        var tableClone = tableEl.cloneNode(true);
                        tableClone.style.width = '100%';
                        tableClone.style.fontSize = '10px';
                        tableClone.style.borderCollapse = 'collapse';
                        tableClone.style.border = '1px solid #e2e8f0';

                        var tbody = tableClone.querySelector('tbody');
                        if (tbody) {
                            var rows = tbody.querySelectorAll('tr');
                            if (rows.length > 9) {
                                for (var rIdx = 9; rIdx < rows.length; rIdx++) rows[rIdx].remove();
                            }
                        }

                        var tableContainer = document.createElement('div');
                        tableContainer.style.width = '100%';
                        tableContainer.style.overflow = 'hidden';
                        if (cardTitle) {
                            var tTitle = document.createElement('div');
                            tTitle.style.fontSize = '11pt';
                            tTitle.style.fontWeight = 'bold';
                            tTitle.style.color = '#1e293b';
                            tTitle.style.marginBottom = '6px';
                            tTitle.innerText = cardTitle;
                            tableContainer.appendChild(tTitle);
                        }
                        tableContainer.appendChild(tableClone);
                        tableContainer.innerHTML += '<div style="font-size:9px; color:#94a3b8; margin-top:4px;">* Exibição dos primeiros registros para modo apresentação.</div>';
                        bodyDiv.appendChild(tableContainer);
                    } else {
                        bodyDiv.innerHTML = '<div style="color:#64748b;">' + card.innerHTML + '</div>';
                    }

                    slide.appendChild(bodyDiv);

                    var footerDiv = document.createElement('div');
                    footerDiv.style.borderTop = '1px solid #f1f5f9';
                    footerDiv.style.paddingTop = '6px';
                    footerDiv.style.fontSize = '8.5px';
                    footerDiv.style.color = '#94a3b8';
                    footerDiv.style.display = 'flex';
                    footerDiv.style.justifyContent = 'space-between';
                    footerDiv.innerHTML = '<span>Painel Analítico Automatizado • BI Master</span><span>Slide ' + (i + 1) + ' de ' + turns.length + '</span>';
                    slide.appendChild(footerDiv);

                    wrapper.appendChild(slide);
                }
            } else {
                // MODO RETRATO: RELATÓRIO EXECUTIVO FORMAL (SEM CORTES À DIREITA)
                var repHeader = document.createElement('div');
                repHeader.style.borderBottom = '2.5px solid #1e40af';
                repHeader.style.paddingBottom = '10px';
                repHeader.style.marginBottom = '22px';
                repHeader.style.boxSizing = 'border-box';
                repHeader.style.width = '100%';
                repHeader.innerHTML = '<div style="display:flex; justify-content:space-between; align-items:flex-end;">' +
                    '<div><h2 style="margin:0; color:#1e40af; font-size:17pt; font-weight:800;">RELATÓRIO EXECUTIVO ANALÍTICO</h2>' +
                    '<div style="font-size:10pt; color:#64748b; margin-top:4px;">BI Enterprise • Inteligência Analítica e Decisão</div></div>' +
                    '<div style="text-align:right; font-size:9pt; color:#64748b;">Data: ' + new Date().toLocaleDateString('pt-BR') + '<br>Base: <strong>' + STATE.activeSource.toUpperCase() + '</strong></div>' +
                    '</div>';
                wrapper.appendChild(repHeader);

                for (var j = 0; j < turns.length; j++) {
                    var itemR = turns[j];
                    var cardR = itemR.card;
                    var cardTitleR = cardR.querySelector('.card-title') ? cardR.querySelector('.card-title').innerText.trim() : '';

                    var section = document.createElement('div');
                    section.style.marginBottom = '20px';
                    section.style.pageBreakInside = 'avoid';
                    section.style.breakInside = 'avoid';
                    section.style.border = '1px solid #cbd5e1';
                    section.style.borderRadius = '10px';
                    section.style.padding = '14px 16px';
                    section.style.background = '#ffffff';
                    section.style.boxSizing = 'border-box';
                    section.style.width = '100%';
                    section.style.maxWidth = '100%';
                    section.style.wordBreak = 'break-word';
                    section.style.overflow = 'hidden';

                    var qDiv = document.createElement('div');
                    qDiv.style.fontSize = '12pt';
                    qDiv.style.fontWeight = '800';
                    qDiv.style.color = '#0f172a';
                    qDiv.style.marginBottom = '12px';
                    qDiv.style.borderLeft = '4px solid #3b82f6';
                    qDiv.style.paddingLeft = '10px';
                    qDiv.style.lineHeight = '1.35';
                    qDiv.style.wordBreak = 'break-word';
                    qDiv.innerText = itemR.prompt;
                    section.appendChild(qDiv);

                    var canvasR = cardR.querySelector('canvas');
                    var kpiElR = cardR.querySelector('.kpi-value');
                    var textElR = cardR.querySelector('.analysis-text');
                    var tableElR = cardR.querySelector('table');

                    if (canvasR) {
                        var imgR = document.createElement('img');
                        imgR.src = canvasR.toDataURL('image/png');
                        imgR.style.maxHeight = '90mm';
                        imgR.style.maxWidth = '100%';
                        imgR.style.display = 'block';
                        imgR.style.margin = '0 auto';
                        imgR.style.objectFit = 'contain';
                        if (cardTitleR) {
                            var tR = document.createElement('div');
                            tR.style.fontSize = '11pt';
                            tR.style.fontWeight = 'bold';
                            tR.style.color = '#334155';
                            tR.style.marginBottom = '8px';
                            tR.style.textAlign = 'center';
                            tR.innerText = cardTitleR;
                            section.appendChild(tR);
                        }
                        section.appendChild(imgR);
                    } else if (kpiElR) {
                        var kpiBoxR = document.createElement('div');
                        kpiBoxR.style.textAlign = 'center';
                        kpiBoxR.style.padding = '15px';
                        kpiBoxR.innerHTML = '<div style="font-size:3.5rem; font-weight:900; color:#1e40af; line-height:1;">' + kpiElR.innerText + '</div>' +
                            '<div style="font-size:1.1rem; font-weight:bold; color:#64748b; margin-top:10px;">' + cardTitleR + '</div>';
                        section.appendChild(kpiBoxR);
                    } else if (textElR) {
                        var textBoxR = document.createElement('div');
                        textBoxR.style.fontSize = '10pt';
                        textBoxR.style.lineHeight = '1.6';
                        textBoxR.style.color = '#1e293b';
                        textBoxR.style.wordBreak = 'break-word';
                        textBoxR.style.overflowWrap = 'break-word';
                        textBoxR.style.boxSizing = 'border-box';
                        textBoxR.style.width = '100%';
                        textBoxR.innerHTML = (cardTitleR ? '<h4 style="margin:0 0 8px 0; color:#1e40af; font-size:11.5pt;">' + cardTitleR + '</h4>' : '') + textElR.innerHTML;
                        section.appendChild(textBoxR);
                    } else if (tableElR) {
                        var tableCloneR = tableElR.cloneNode(true);
                        tableCloneR.style.width = '100%';
                        tableCloneR.style.maxWidth = '100%';
                        tableCloneR.style.fontSize = '9.5px';
                        tableCloneR.style.borderCollapse = 'collapse';
                        tableCloneR.style.border = '1px solid #e2e8f0';
                        tableCloneR.style.tableLayout = 'auto';
                        tableCloneR.style.wordBreak = 'break-word';
                        tableCloneR.style.boxSizing = 'border-box';
                        if (cardTitleR) {
                            var tTitleR = document.createElement('div');
                            tTitleR.style.fontSize = '11pt';
                            tTitleR.style.fontWeight = 'bold';
                            tTitleR.style.marginBottom = '6px';
                            tTitleR.innerText = cardTitleR;
                            section.appendChild(tTitleR);
                        }
                        section.appendChild(tableCloneR);
                    }

                    wrapper.appendChild(section);
                }
            }

            // Isola o wrapper na viewport para captura
            mainEl.style.display = 'none';
            sidebarEl.style.display = 'none';
            document.body.appendChild(wrapper);
            window.scrollTo(0, 0);

            var opt = {
                margin: [0, 0, 0, 0],
                filename: (isLandscape ? 'Apresentacao_BI_' : 'Relatorio_BI_') + new Date().toISOString().slice(0, 10) + '.pdf',
                image: {
                    type: 'jpeg',
                    quality: 0.98
                },
                html2canvas: {
                    scale: 2,
                    useCORS: true,
                    logging: false,
                    scrollX: 0,
                    scrollY: 0
                },
                jsPDF: {
                    unit: 'mm',
                    format: 'a4',
                    orientation: orientation
                },
                pagebreak: {
                    mode: ['css', 'legacy']
                }
            };

            try {
                await new Promise(function(resolve) {
                    setTimeout(resolve, 300);
                });
                await html2pdf().set(opt).from(wrapper).save();
            } finally {
                if (wrapper && wrapper.parentNode) {
                    wrapper.parentNode.removeChild(wrapper);
                }
                mainEl.style.display = '';
                sidebarEl.style.display = '';
                window.scrollTo(0, savedScrollY);
                echartInstances.forEach(function(i) {
                    i.resize();
                });
            }
        }

        async function send() {
            var text = input.value.trim();
            if (!text) return;

            var activeSource = STATE.activeSource;
            var turnId = 'turn-' + Date.now();

            // Bloco unificado da pergunta e de seus resultados
            var turnBlock = document.createElement('div');
            turnBlock.className = 'chat-turn-block';
            turnBlock.id = turnId;

            var sourceTagHtml = '';
            if (activeSource === 'custom') {
                sourceTagHtml = '<span class="source-tag">CUSTOM: ' + (STATE.hasCustomDb ? STATE.customDbInfo.split(' ')[0] : 'MYSQL') + '</span>';
            } else if (activeSource === 'csv') {
                sourceTagHtml = '<span class="source-tag">CSV: ' + (STATE.hasCsv ? STATE.csvInfo.split(' ')[0] : 'PLANILHA') + '</span>';
            }

            var aiId = 'ai-' + Date.now();

            // Linha do Usuário com botão discreto (x) para remoção e botão de cópia limpa
            turnBlock.innerHTML = '<div class="msg-user-row">' +
                '<button class="user-del-btn" onclick="removeTurnBlock(\'' + turnId + '\')" title="Remover este resultado do painel"><i class="fas fa-times"></i></button>' +
                '<button class="user-copy-btn" onclick="intelligentCopy(this)" title="Copiar pergunta"><i class="far fa-copy"></i></button>' +
                '<div class="msg-user-bubble"><span class="user-prompt-text">' + text + '</span>' + sourceTagHtml + '</div>' +
                '</div>' +
                '<div id="' + aiId + '"><div class="card" style="width:fit-content"><i class="fas fa-circle-notch fa-spin"></i> Processando dados e gerando visualizações...</div></div>';

            log.appendChild(turnBlock);
            input.value = '';
            btn.disabled = true;
            log.scrollTop = log.scrollHeight;

            try {
                var response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        prompt: text,
                        source: activeSource
                    })
                });
                var res = await response.json();
                var aiDiv = document.getElementById(aiId);

                if (res.status === 'error') {
                    aiDiv.innerHTML = '<div class="card" style="color:#dc2626"><i class="fas fa-exclamation-triangle"></i> Erro: ' + res.message + '</div>';
                } else {
                    aiDiv.innerHTML = '<div class="dashboard-grid"></div>';
                    res.widgets.forEach(function(w, i) {
                        renderEChartWidget(aiDiv.querySelector('.dashboard-grid'), w, aiId + i);
                    });
                }
            } catch (e) {
                document.getElementById(aiId).innerHTML = "<div class='card' style='color:#dc2626'>Erro de conexão com o servidor.</div>";
            }
            btn.disabled = false;
            log.scrollTop = log.scrollHeight;
        }

        function renderEChartWidget(grid, wData, uId) {
            var config = wData.config,
                data = wData.data,
                labels = config.colunas || {};
            var card = document.createElement('div');
            card.className = 'card';
            card.innerHTML = '<button class="copy-btn" onclick="copyWidget(this)" title="Copiar"><i class="far fa-copy"></i></button><div class="card-title">' + config.titulo + '</div><div id="body-' + uId + '"></div>';
            grid.appendChild(card);
            var body = document.getElementById('body-' + uId);

            if (!data || data.length === 0) {
                body.innerHTML = "<i style='color:#64748b;'>Sem registros encontrados para esta consulta.</i>";
                return;
            }

            if (config.visualizacao === 'texto') {
                body.innerHTML = '<div class="analysis-text">' + wData.analysis_html + '</div>';
            } else if (config.visualizacao === 'kpi') {
                var val = Object.values(data[0]).find(function(v) {
                    return !isNaN(parseFloat(v));
                }) || Object.values(data[0])[0];
                body.innerHTML = '<div style="text-align:center;"><div class="kpi-value" style="font-size:3.5rem;font-weight:900;color:var(--primary);">' + val + '</div><div style="font-weight:700;color:#64748b;text-transform:uppercase;">' + config.titulo + '</div></div>';
            } else if (config.visualizacao === 'tabela') {
                renderTableBI(body, data, labels, uId, config.titulo);
            } else {
                var chartDom = document.createElement('div');
                chartDom.className = 'chart-div';
                body.appendChild(chartDom);
                var myChart = echarts.init(chartDom, null, {
                    renderer: 'canvas',
                    preserveDrawingBuffer: true
                });
                echartInstances.push(myChart);

                if (window.ResizeObserver) {
                    new ResizeObserver(function() {
                        myChart.resize();
                    }).observe(chartDom);
                }

                var opt = {};
                var total = data.reduce(function(s, d) {
                    return s + (parseFloat(Object.values(d)[1]) || 0);
                }, 0);

                if (config.visualizacao === 'nuvem') {
                    var freq = {};
                    data.forEach(function(d) {
                        var words = Object.values(d).join(' ').toLowerCase().match(/[a-záàâãéèêíïóôõöúç]{4,}/g);
                        if (words) {
                            words.forEach(function(w) {
                                freq[w] = (freq[w] || 0) + 1;
                            });
                        }
                    });
                    opt = {
                        series: [{
                            type: 'wordCloud',
                            shape: 'circle',
                            sizeRange: [12, 55],
                            textStyle: {
                                fontWeight: 'bold',
                                color: function() {
                                    return 'rgb(' + [Math.round(Math.random() * 150), Math.round(Math.random() * 150), Math.round(Math.random() * 150)].join(',') + ')';
                                }
                            },
                            data: Object.keys(freq).map(function(k) {
                                return {
                                    name: k,
                                    value: freq[k]
                                };
                            })
                        }]
                    };
                } else if (config.visualizacao === 'heatmap') {
                    var xK = Array.from(new Set(data.map(function(d) {
                        return Object.values(d)[0];
                    }))).sort();
                    var yK = Array.from(new Set(data.map(function(d) {
                        return Object.values(d)[1];
                    }))).sort();
                    opt = {
                        grid: {
                            containLabel: true,
                            left: 10,
                            right: 10,
                            bottom: 45,
                            top: 20
                        },
                        tooltip: {
                            position: 'top'
                        },
                        xAxis: {
                            type: 'category',
                            data: xK,
                            axisLabel: {
                                rotate: 30
                            }
                        },
                        yAxis: {
                            type: 'category',
                            data: yK
                        },
                        visualMap: {
                            min: 0,
                            max: Math.max.apply(Math, data.map(function(d) {
                                return parseInt(Object.values(d)[2]) || 1;
                            })),
                            orient: 'horizontal',
                            left: 'center',
                            bottom: 0
                        },
                        series: [{
                            type: 'heatmap',
                            data: data.map(function(d) {
                                return [xK.indexOf(Object.values(d)[0]), yK.indexOf(Object.values(d)[1]), Object.values(d)[2]];
                            }),
                            label: {
                                show: true
                            }
                        }]
                    };
                } else if (config.visualizacao === 'timeline') {
                    var categories = data.map(function(d) {
                        var raw = String(Object.values(d)[0] || 'Evento');
                        return raw.length > 32 ? raw.substring(0, 29) + '...' : raw;
                    });
                    var chartData = data.map(function(d, i) {
                        var fullName = String(Object.values(d)[0] || 'Evento');
                        var sTime = new Date(Object.values(d)[1]).getTime();
                        var eTime = new Date(Object.values(d)[2]).getTime();
                        return {
                            name: fullName,
                            value: [i, sTime, eTime],
                            itemStyle: {
                                normal: {
                                    color: '#3b82f6'
                                }
                            }
                        };
                    });

                    opt = {
                        tooltip: {
                            formatter: function(p) {
                                return '<b>' + p.name + '</b><br/>' +
                                    new Date(p.value[1]).toLocaleDateString('pt-BR') + ' até ' +
                                    new Date(p.value[2]).toLocaleDateString('pt-BR');
                            }
                        },
                        grid: {
                            containLabel: true,
                            height: Math.max(categories.length * 32, 160),
                            bottom: 35,
                            top: 40,
                            left: 15,
                            right: 25
                        },
                        xAxis: {
                            type: 'time',
                            position: 'top',
                            axisLabel: {
                                color: '#64748b',
                                fontSize: 10
                            }
                        },
                        yAxis: {
                            data: categories,
                            inverse: true,
                            axisLabel: {
                                fontSize: 10,
                                color: '#1e293b',
                                fontWeight: 'bold',
                                width: 140,
                                overflow: 'truncate'
                            }
                        },
                        series: [{
                            type: 'custom',
                            renderItem: function(params, api) {
                                var categoryIndex = api.value(0);
                                var start = api.coord([api.value(1), categoryIndex]);
                                var end = api.coord([api.value(2), categoryIndex]);
                                var height = Math.min(api.size([0, 1])[1] * 0.6, 22);
                                var rectShape = echarts.graphic.clipRectByRect({
                                    x: start[0],
                                    y: start[1] - height / 2,
                                    width: Math.max(end[0] - start[0], 4),
                                    height: height
                                }, {
                                    x: params.coordSys.x,
                                    y: params.coordSys.y,
                                    width: params.coordSys.width,
                                    height: params.coordSys.height
                                });
                                return rectShape && {
                                    type: 'rect',
                                    transition: ['shape'],
                                    shape: rectShape,
                                    style: api.style()
                                };
                            },
                            encode: {
                                x: [1, 2],
                                y: 0
                            },
                            data: chartData
                        }]
                    };
                } else if (config.visualizacao === 'pizza') {
                    opt = {
                        tooltip: {
                            trigger: 'item',
                            formatter: '{b}: {c} ({d}%)'
                        },
                        legend: {
                            orient: 'horizontal',
                            bottom: 10,
                            left: 'center'
                        },
                        series: [{
                            type: 'pie',
                            radius: '65%',
                            center: ['50%', '42%'],
                            data: data.map(function(d) {
                                return {
                                    name: Object.values(d)[0],
                                    value: Object.values(d)[1]
                                };
                            }),
                            label: {
                                show: true,
                                formatter: '{b}: {d}%'
                            }
                        }]
                    };
                } else if (config.visualizacao === 'grafo') {
                    var nodes = [],
                        links = [],
                        ent = new Set(),
                        degree = {};
                    data.forEach(function(d) {
                        var s = Object.values(d)[0],
                            t = Object.values(d)[1],
                            v = Object.values(d)[2];
                        ent.add(s);
                        ent.add(t);
                        degree[s] = (degree[s] || 0) + 1;
                        degree[t] = (degree[t] || 0) + 1;
                        links.push({
                            source: s,
                            target: t,
                            value: v
                        });
                    });
                    var max = Math.max.apply(Math, Object.values(degree));
                    ent.forEach(function(e) {
                        var deg = degree[e] || 0,
                            r = deg / max;
                        nodes.push({
                            name: e,
                            draggable: true,
                            symbolSize: Math.max(20, 18 + deg * 4),
                            itemStyle: {
                                color: r >= 0.8 ? '#ef4444' : r >= 0.5 ? '#f59e0b' : '#3b82f6'
                            },
                            label: {
                                show: true,
                                position: 'right'
                            }
                        });
                    });
                    opt = {
                        tooltip: {
                            formatter: function(p) {
                                return p.dataType === 'node' ? '<b>' + p.data.name + '</b>' : p.data.source + ' → ' + p.data.target;
                            }
                        },
                        series: [{
                            type: 'graph',
                            layout: 'force',
                            data: nodes,
                            links: links,
                            roam: true,
                            lineStyle: {
                                color: '#999',
                                opacity: 0.6,
                                width: 1.5
                            },
                            force: {
                                repulsion: 600,
                                edgeLength: 200
                            }
                        }]
                    };
                } else {
                    opt = {
                        grid: {
                            containLabel: true,
                            left: 10,
                            right: 15,
                            bottom: 45,
                            top: 30
                        },
                        tooltip: {
                            trigger: 'axis'
                        },
                        xAxis: {
                            type: 'category',
                            data: data.map(function(d) {
                                return formatDateValue(Object.values(d)[0]);
                            }),
                            axisLabel: {
                                interval: 0,
                                rotate: 30,
                                fontSize: 10
                            }
                        },
                        yAxis: {
                            type: 'value'
                        },
                        series: [{
                            type: config.visualizacao === 'linha' ? 'line' : 'bar',
                            data: data.map(function(d) {
                                return Object.values(d)[1];
                            }),
                            smooth: 0.4,
                            itemStyle: {
                                color: '#3b82f6'
                            },
                            label: {
                                show: true,
                                position: 'top',
                                formatter: function(p) {
                                    var prc = total > 0 ? ((p.value / total) * 100).toFixed(1) : 0;
                                    return p.value + (total > 0 ? '\n(' + prc + '%)' : '');
                                },
                                fontSize: 9
                            }
                        }]
                    };
                }
                myChart.setOption(opt);
            }
        }

        function renderTableBI(container, data, labels, uId, title) {
            biRegistry[uId] = {
                data: data.slice(0),
                filtered: data.slice(0),
                labels: labels,
                sortK: null,
                sortA: true,
                page: 1,
                title: title,
                draw: function() {
                    var tArea = document.getElementById('t-area-' + uId),
                        pArea = document.getElementById('p-area-' + uId),
                        cntArea = document.getElementById('cnt-' + uId);
                    cntArea.innerText = 'Total: ' + this.filtered.length + ' registros';
                    var start = (this.page - 1) * 10,
                        items = this.filtered.slice(start, start + 10),
                        keys = Object.keys(data[0]).slice(0, 6);
                    var h = '<div class="table-responsive"><table><thead><tr>';
                    keys.forEach(function(k) {
                        h += '<th onclick="biRegistry[\'' + uId + '\'].sort(\'' + k + '\')">' + getFieldLabel(k, labels) + ' <i class="fas fa-sort"></i></th>';
                    });
                    h += '</tr></thead><tbody>';
                    items.forEach(function(row, index) {
                        h += '<tr onclick="triggerModal(\'' + uId + '\', ' + (start + index) + ')">';
                        keys.forEach(function(k) {
                            var c = row[k] || '';
                            if (k.toLowerCase().indexOf('pdf') !== -1 && c !== '') {
                                c = '<a href="' + (c.indexOf('http') === 0 ? c : FILE_ROOT + c) + '" target="_blank" style="color:var(--secondary);font-weight:bold;">PDF</a>';
                            } else {
                                c = formatDateValue(c);
                            }
                            h += '<td>' + c + '</td>';
                        });
                        h += '</tr>';
                    });
                    tArea.innerHTML = h + '</tbody></table></div>';
                    pArea.innerHTML = '';
                    var totalP = Math.ceil(this.filtered.length / 10);
                    if (totalP > 1) {
                        var self = this;
                        for (var i = 1; i <= totalP; i++) {
                            (function(pageIdx) {
                                var b = document.createElement('button');
                                b.className = 'page-btn ' + (pageIdx === self.page ? 'active' : '');
                                b.innerText = pageIdx;
                                b.onclick = function() {
                                    self.page = pageIdx;
                                    self.draw();
                                };
                                pArea.appendChild(b);
                            })(i);
                        }
                    }
                },
                sort: function(k) {
                    this.sortA = (this.sortK === k) ? !this.sortA : true;
                    this.sortK = k;
                    var sa = this.sortA;
                    this.filtered.sort(function(a, b) {
                        return (a[k] || '').toString().localeCompare((b[k] || '').toString(), undefined, {
                            numeric: true
                        }) * (sa ? 1 : -1);
                    });
                    this.page = 1;
                    this.draw();
                },
                filter: function(val) {
                    this.filtered = this.data.filter(function(d) {
                        return norm(Object.values(d).map(formatDateValue).join(' ')).indexOf(norm(val)) !== -1 || norm(Object.values(d).join(' ')).indexOf(norm(val)) !== -1;
                    });
                    this.page = 1;
                    this.draw();
                }
            };
            container.innerHTML = '<div class="table-toolbar"><input type="text" class="search-table" placeholder="Filtrar dados..." oninput="biRegistry[\'' + uId + '\'].filter(this.value)"><div class="record-counter" id="cnt-' + uId + '"></div><button class="export-btn" onclick="exportCSV(biRegistry[\'' + uId + '\'].filtered, biRegistry[\'' + uId + '\'].labels, biRegistry[\'' + uId + '\'].title)"><i class="fas fa-file-excel"></i> EXCEL</button></div><div id="t-area-' + uId + '"></div><div id="p-area-' + uId + '" class="pagination"></div>';
            biRegistry[uId].draw();
        }

        function triggerModal(uId, index) {
            var state = biRegistry[uId];
            if (!state) return;
            var data = state.filtered[index],
                labels = state.labels;
            window.lastModalData = data;
            window.lastModalLabels = labels;

            var body = document.getElementById('m-body');
            body.innerHTML = '';
            for (var key in data) {
                var label = getFieldLabel(key, labels),
                    val = data[key] || '---',
                    display = val;
                if (String(val).toLowerCase().indexOf('.pdf') !== -1 && val !== '---') {
                    display = '<a href="' + (val.indexOf('http') === 0 ? val : FILE_ROOT + val) + '" target="_blank" style="background:#eff6ff; color:var(--secondary); padding:8px 14px; border-radius:8px; text-decoration:none; font-weight:800; display:inline-flex; align-items:center; gap:8px;"><i class="fas fa-file-pdf"></i> VER DOCUMENTO</a>';
                } else if (val !== '---') {
                    display = formatDateValue(val);
                }
                body.innerHTML += '<div style="margin-bottom:12px; border-bottom:1px solid #f1f5f9; padding-bottom:6px;"><div style="font-size:10px; text-transform:uppercase; color:#64748b; font-weight:bold;">' + label + '</div><div style="font-size:14px; color:#1e293b; line-height:1.5;">' + display + '</div></div>';
            }
            modalOverlay.style.display = 'flex';
        }

        document.getElementById('btn-modal-copy').onclick = async function() {
            if (!window.lastModalData) return;
            var h = '<table border="1" style="border-collapse:collapse; width:100%; font-family:Arial;">',
                p = "";
            for (var k in window.lastModalData) {
                var l = getFieldLabel(k, window.lastModalLabels),
                    val = formatDateValue(window.lastModalData[k]) || '---';
                h += '<tr><th style="padding:8px; background:#f1f5f9; text-align:left; width:35%">' + l + '</th><td style="padding:8px;">' + val + '</td></tr>';
                p += l + ': ' + val + '\n';
            }
            h += '</table>';
            try {
                await navigator.clipboard.write([new ClipboardItem({
                    "text/html": new Blob([h], {
                        type: "text/html"
                    }),
                    "text/plain": new Blob([p], {
                        type: "text/plain"
                    })
                })]);
            } catch (e) {
                var el = document.createElement('textarea');
                el.value = p;
                document.body.appendChild(el);
                el.select();
                document.execCommand('copy');
                document.body.removeChild(el);
            }
            this.innerText = 'COPIADO!';
            var self = this;
            setTimeout(function() {
                self.innerText = 'COPIAR DADOS';
            }, 2000);
        };

        // CÓPIA INTELIGENTE (PERGUNTA LIMPA SEM TAGS)
        async function intelligentCopy(button) {
            var card = button.closest('.card'),
                userRow = button.closest('.msg-user-row');
            var htmlContent = '',
                plainContent = '';

            if (userRow) {
                var promptSpan = userRow.querySelector('.user-prompt-text');
                if (promptSpan) {
                    plainContent = promptSpan.innerText.trim();
                } else {
                    var bubble = userRow.querySelector('.msg-user-bubble');
                    var clone = bubble ? bubble.cloneNode(true) : null;
                    if (clone) {
                        var tag = clone.querySelector('.source-tag');
                        if (tag) tag.remove();
                        plainContent = clone.innerText.trim();
                    }
                }
                htmlContent = '<p style="font-family: Arial; font-size: 11pt; color: #1e293b;">' + plainContent + '</p>';
            } else if (card) {
                var titleEl = card.querySelector('.card-title');
                var titleText = titleEl ? titleEl.innerText.trim() : '';
                var textDiv = card.querySelector('.analysis-text'),
                    canvas = card.querySelector('canvas'),
                    table = card.querySelector('table'),
                    kpiVal = card.querySelector('.kpi-value');

                if (textDiv) {
                    htmlContent = '<div style="font-family: Arial; font-size: 11pt;"><h3 style="color:#1e40af;">' + titleText + '</h3>' + textDiv.innerHTML + '</div>';
                    plainContent = titleText + '\n\n' + textDiv.innerText.trim();
                } else if (canvas) {
                    try {
                        canvas.toBlob(async function(blob) {
                            await navigator.clipboard.write([new ClipboardItem({
                                "image/png": blob
                            })]);
                            showCopySuccess(button);
                        });
                        return;
                    } catch (e) {
                        console.error(e);
                    }
                } else if (table) {
                    var cleanTable = table.cloneNode(true);
                    cleanTable.setAttribute('border', '1');
                    cleanTable.style.borderCollapse = 'collapse';
                    htmlContent = '<div style="font-family: Arial;"><h3 style="color:#1e40af;">' + titleText + '</h3>' + cleanTable.outerHTML + '</div>';
                    plainContent = titleText + '\n\n' + table.innerText.trim();
                } else if (kpiVal) {
                    htmlContent = '<div style="font-family: Arial; text-align:center;"><h2>' + kpiVal.innerText + '</h2><p>' + titleText + '</p></div>';
                    plainContent = titleText + ': ' + kpiVal.innerText.trim();
                }
            }

            if (htmlContent || plainContent) {
                try {
                    await navigator.clipboard.write([new ClipboardItem({
                        "text/html": new Blob([htmlContent], {
                            type: "text/html"
                        }),
                        "text/plain": new Blob([plainContent], {
                            type: "text/plain"
                        })
                    })]);
                    showCopySuccess(button);
                } catch (err) {
                    navigator.clipboard.writeText(plainContent);
                    showCopySuccess(button);
                }
            }
        }

        function showCopySuccess(btn) {
            var original = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-check" style="color:var(--success)"></i>';
            setTimeout(function() {
                btn.innerHTML = original;
            }, 2000);
        }

        function exportCSV(data, labels, title) {
            if (!data || !data.length) return;
            var csv = "\uFEFF" + Object.keys(data[0]).map(function(k) {
                return getFieldLabel(k, labels);
            }).join(";") + "\r\n";
            data.forEach(function(r) {
                csv += Object.values(r).map(function(v) {
                    return '"' + String(formatDateValue(v) || '').replace(/"/g, '""') + '"';
                }).join(";") + "\r\n";
            });
            var link = document.createElement("a");
            link.href = URL.createObjectURL(new Blob([csv], {
                type: 'text/csv;charset=utf-8'
            }));
            link.download = (title || 'exportacao') + ".csv";
            link.click();
        }

        window.copyWidget = function(btn) {
            intelligentCopy(btn);
        };
        input.onkeydown = function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                send();
            }
        };
        btn.onclick = send;
    </script>
</body>

</html>