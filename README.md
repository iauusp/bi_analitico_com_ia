# Análises de base de dados com auxílio de IA

**Sistema de BI Analítico Conversacional**

### Manual de configuração, intervenção e uso

Guia completo para novos utilizadores: pontos de intervenção, configuração de bases de dados, chave Gemini, schema DDL, perguntas exemplo, autenticação, RAG e operação do sistema.

| PHP 5.6 → 8.3+ | Google Gemini | MySQL + CSV |
| --- | --- | --- |
| Compatibilidade total | Text-to-SQL em tempo real | Multi-fonte de dados |

**Sistema desenvolvido por:** Seção de Informática — IAU / USP  
[iau.sti@sc.usp.br](mailto:iau.sti@sc.usp.br)

> Documento gerado para facilitar a instalação e adaptação do script em novos ambientes e bases de dados.

## Índice

Use os links abaixo para ir diretamente à secção correspondente.

- [1. Visão Geral do Sistema](#sec-1)
- [2. Requisitos e Preparação do Ambiente](#sec-2)
- [3. Pontos de Intervenção — Zona de Controle](#sec-3)
  - [3.1 Autenticação (Login e Senha)](#sec-3-1)
  - [3.2 Flags de Funcionalidades](#sec-3-2)
  - [3.3 Orientação do PDF](#sec-3-3)
- [4. Configuração da Base de Dados Padrão](#sec-4)
- [5. Estrutura do Banco (DDL / Schema)](#sec-5)
- [6. Exemplos de Perguntas (Help Panel)](#sec-6)
- [7. Chave e Modelos da API Google Gemini](#sec-7)
- [8. Structured RAG (Schema Pruning)](#sec-8)
- [9. Diretório de Arquivos / Anexos](#sec-9)
- [10. Como Configurar para Outras Bases de Dados](#sec-10)
  - [10.1 Base Customizada (MySQL externo)](#sec-10-1)
  - [10.2 Análise de Planilhas CSV](#sec-10-2)
- [11. Como Utilizar o Sistema (Guia de Uso)](#sec-11)
  - [11.1 Tela de Login](#sec-11-1)
  - [11.2 Interface Principal](#sec-11-2)
  - [11.3 Formulando Perguntas](#sec-11-3)
  - [11.4 Widgets e Visualizações](#sec-11-4)
  - [11.5 Exportação PDF (Apresentação / Relatório)](#sec-11-5)
- [12. Segurança (Read-Only)](#sec-12)
- [13. Fluxo Interno Resumido](#sec-13)
- [14. Checklist Rápido de Implantação](#sec-14)
- [15. Resolução de Problemas Comuns](#sec-15)
- [16. Anexo — Localização das Variáveis no Código](#sec-16)
- [17. Disclaimer e Licença de Uso](#sec-17)

<a id="sec-1"></a>

## 1. Visão Geral do Sistema

O sistema **Análises de base de dados com auxílio de IA** é uma solução de Business Intelligence conversacional baseada em PHP que utiliza a API do Google Gemini para transformar perguntas em linguagem natural em consultas SQL (Text-to-SQL) e gerar visualizações interativas (gráficos ECharts, tabelas, KPIs, heatmaps, nuvens de palavras, timelines e grafos).

**Principais capacidades:**

- Text-to-SQL analítico em tempo real com Gemini.
- Segurança estrita Read-Only (dupla camada: engine MySQL + validação regex em PHP).
- Motor híbrido de CSV (PDO SQLite → SQLite3 nativo → PHP puro).
- Descoberta automática de tabelas (Auto-Schema) em bases customizadas.
- Geração dinâmica de perguntas sugeridas pela IA.
- Memória conversacional com auto-recuperação quando não há resultados.
- Exportação em PDF no modo Apresentação (slides) ou Relatório formal.
- Gestão protegida da chave de API (nunca exposta no cliente).

**Arquitetura em uma frase:** o utilizador faz uma pergunta → o Gemini gera um JSON com SQL + tipo de visualização → o PHP executa a consulta (apenas `SELECT`/`WITH`) → os dados são renderizados no frontend com ECharts ou HTML.

<a id="sec-2"></a>

## 2. Requisitos e Preparação do Ambiente

Antes de editar o script, confirme o ambiente:

| Item | Requisito |
| --- | --- |
| PHP | 5.6 até 8.3+ (testado e compatível) |
| Extensões PHP | `mysqli`, `curl`, `json`, `session`, `mbstring` (recomendado). Opcional: `pdo_sqlite` ou `SQLite3` para CSV rápido. |
| Servidor Web | Apache, Nginx ou similar com suporte a PHP |
| MySQL / MariaDB | Acesso de leitura (`SELECT`) à base que será analisada |
| Chave Gemini | Conta Google AI Studio / Google Cloud com API key válida |
| Navegador | Chrome, Firefox, Edge modernos (suporte a ES6 + Canvas) |

> **Nota:** O script desativa o reporte estrito do mysqli no PHP 8.1+ e suprime avisos legados de `$HTTP_RAW_POST_DATA` no PHP 5.6 para máxima compatibilidade.

<a id="sec-3"></a>

## 3. Pontos de Intervenção — Zona de Controle

Todas as configurações que um novo utilizador deve ou pode alterar estão concentradas no início do ficheiro PHP, em blocos claramente comentados como **[ZONA DE CONTROLE]**, **[ZONA 1]**, **[ZONA 2]**, etc. Não é necessário alterar a lógica de negócio para colocar o sistema a funcionar.

<a id="sec-3-1"></a>

### 3.1 Autenticação (Login e Senha)

Localização aproximada: linhas iniciais após os comentários de cabeçalho.

```php
$enable_auth = 1; // 1 = Ativa tela de login; 0 = Acesso livre
$auth_user = "admin";
// Senha em MD5 (Padrão "admin123" = 0192023a7bbd73250516f069df18b500)
$auth_pass_md5 = "0192023a7bbd73250516f069df18b500";
```

**O que fazer:**

- Defina `$enable_auth = 1` para exigir login (recomendado em produção).
- Altere `$auth_user` para o nome de utilizador desejado.
- Gere o MD5 da nova senha (ex.: em PHP `echo md5('sua_senha');` ou em sites de hash MD5) e coloque o resultado em `$auth_pass_md5`.

> **Importante:** A senha padrão do script é `admin123`. Mude-a imediatamente antes de expor o sistema.

<a id="sec-3-2"></a>

### 3.2 Flags de Funcionalidades

```php
$enable_default_db = 1; // 1 = Usa a base padrão; 0 = Sem base padrão
$enable_custom_db  = 1; // 1 = Permite conectar outra base MySQL via modal
$enable_csv_upload = 1; // 1 = Permite upload e análise de planilhas CSV
$enable_help_panel = 1; // 1 = Exibe menu lateral de atalhos e perguntas
```

Use estas flags para restringir o que os utilizadores podem fazer. Exemplo: num ambiente só com CSV, desative default e custom (`= 0`).

<a id="sec-3-3"></a>

### 3.3 Orientação do PDF

```php
// "paisagem" (landscape) ou "retrato" (portrait)
$pdf_orientation = "retrato";
```

Define a orientação padrão do relatório PDF. O utilizador ainda pode escolher no momento da exportação (modal com duas opções: Paisagem/Slides ou Retrato/Relatório).

<a id="sec-4"></a>

## 4. Configuração da Base de Dados Padrão

**ZONA 1 —** Credenciais da base MySQL/MariaDB que o sistema usa por defeito.

```php
$db_config = array(
    'host' => 'localhost',
    'port' => 3306,
    'user' => '', // ← PREENCHER
    'pass' => '', // ← PREENCHER
    'name' => ''  // ← PREENCHER (nome do schema/banco)
);
```

> **Ação obrigatória:** preencha `host`, `user`, `pass` e `name` com as credenciais de um utilizador que tenha apenas permissões de leitura (`SELECT`). Não use um utilizador root ou com privilégios de escrita.

Se `$enable_default_db = 0`, esta configuração é ignorada e o sistema obriga a usar Base Customizada ou CSV.

<a id="sec-5"></a>

## 5. Estrutura do Banco (DDL / Schema)

**ZONA 5 —** Variável `$database_schema`. Este texto é enviado ao Gemini como contexto da estrutura dos dados. Quanto mais claro e descritivo for, melhores serão as consultas SQL geradas.

No script original existe um exemplo para a tabela `relatorios` (afastamentos de docentes). Substitua-o pela descrição real das suas tabelas.

**Formato recomendado:**

```php
$database_schema = "
Tabela `minha_tabela`:
- Colunas:
  `id` int(11) NOT NULL (Chave primária),
  `nome` varchar(255) (Nome completo do cliente),
  `data_pedido` date (Data em que o pedido foi feito),
  `valor` decimal(10,2) (Valor total do pedido),
  `status` varchar(50) (Valores: 'Aberto', 'Pago', 'Cancelado')
- Descrição: Cada linha representa um pedido de venda.
- Campos irrelevantes para a maioria das consultas: id_interno.
";
```

**Dicas para um bom schema:**

- Inclua o significado semântico de cada coluna (não só o tipo).
- Indique valores comuns / enums quando existirem.
- Explique o grão da tabela (o que cada linha representa).
- Marque colunas irrelevantes para a maioria das análises.
- Se houver várias tabelas, descreva-as todas (ou use RAG — ver [secção 8](#sec-8)).

Se deixar `$database_schema` vazio e a base padrão estiver ativa, o sistema tenta extrair automaticamente o schema via `SHOW CREATE TABLE` (função `extract_mysql_schema_auto`).

<a id="sec-6"></a>

## 6. Exemplos de Perguntas (Help Panel)

**ZONA 6 —** Array `$help_examples`. Estas frases aparecem no menu lateral como atalhos clicáveis. A primeira pergunta é frequentemente usada como dashboard inicial.

```php
$help_examples = array(
    "Gere um dashboard geral de afastamentos com total...",
    "Listar todos os relatórios",
    "Gráfico de barras: total por país",
    "Nuvem de palavras com as atividades",
    // ... mais exemplos
);
```

Adapte os textos ao domínio dos seus dados. Pode usar dois pontos no final (ex.: "Afastamentos do docente:") para o utilizador completar o valor. Quando a fonte ativa for CSV ou Base Customizada, o sistema gera automaticamente novas sugestões via Gemini (função `generate_ai_suggested_questions`).

<a id="sec-7"></a>

## 7. Chave e Modelos da API Google Gemini

**ZONA 3 —** Credenciais e modelos permitidos.

```php
$gemini_api_key = "SUA_CHAVE_GEMINI_AQUI"; // ← OBRIGATÓRIO

// Modelo padrão inicial
$gemini_model = "gemini-2.0-flash"; // ajuste conforme disponibilidade

// Modelos que o utilizador pode escolher na interface
$gemini_available_models = array(
    "gemini-2.0-flash",
    "gemini-1.5-flash",
    // adicione outros modelos válidos da sua conta
);
```

> **Ação obrigatória:** substitua `SUA_CHAVE_GEMINI_AQUI` pela chave real obtida no [Google AI Studio](https://aistudio.google.com/) ou no Google Cloud Console.

A chave nunca é enviada para o navegador. Toda a comunicação com a API Gemini ocorre no servidor PHP (função `call_gemini_api`). O utilizador pode, opcionalmente, informar uma chave pessoal apenas para a sessão atual (modal "Configurar IA"), sem alterar o ficheiro.

Verifique periodicamente os nomes dos modelos disponíveis na documentação oficial do Gemini, pois a Google atualiza a nomenclatura com frequência.

<a id="sec-8"></a>

## 8. Structured RAG (Schema Pruning)

```php
$enable_structured_rag = 0; // 0 = desligado (padrão); 1 = ligado
```

Quando ativado (`= 1`) e a fonte for MySQL, antes de gerar o SQL principal o sistema envia o schema completo + a pergunta a um "router" Gemini que devolve apenas as tabelas/colunas necessárias (Schema Pruning). Útil em bases com dezenas de tabelas, para reduzir tokens e melhorar a precisão.

Em bases pequenas ou com schema já bem descrito em `$database_schema`, mantenha desligado (`0`) para poupar uma chamada à API.

<a id="sec-9"></a>

## 9. Diretório de Arquivos / Anexos

```php
$base_file_url = ""; // Ex.: "https://seudominio.com/uploads/" ou "/arquivos/"
```

Usado quando uma coluna contém caminho de ficheiro (ex.: `pdf_path`). O frontend concatena este prefixo para montar o link de download/visualização. Se os caminhos já forem absolutos (começam por `http`), o prefixo é ignorado.

<a id="sec-10"></a>

## 10. Como Configurar para Outras Bases de Dados

<a id="sec-10-1"></a>

### 10.1 Base Customizada (MySQL externo)

Não é necessário editar o código. Na interface:

1. Clique em **Conectar Outro Banco MySQL** (menu lateral ou seletor de fonte).
2. Preencha Host, Porta, Utilizador, Senha e Nome do Banco.
3. O sistema testa a conexão em modo READ ONLY, extrai o schema automaticamente e gera perguntas sugeridas via IA.
4. A fonte passa a ser a base customizada para as próximas consultas (sessão PHP).

Os dados ficam apenas em `$_SESSION['custom_db']` — não são gravados no ficheiro PHP.

<a id="sec-10-2"></a>

### 10.2 Análise de Planilhas CSV

Também sem alteração de código:

1. Clique em **Carregar Planilha CSV**.
2. Selecione o ficheiro (`.csv`). O sistema deteta o delimitador (vírgula, ponto-e-vírgula, tab, etc.).
3. Os dados são estruturados numa tabela virtual `dados_csv`.
4. Motor usado (por ordem de preferência): PDO SQLite → SQLite3 → PHP nativo em memória.
5. Perguntas sugeridas e um dashboard inicial são gerados automaticamente.

Para ficheiros muito grandes, ative a extensão `pdo_sqlite` no PHP para melhor performance.

<a id="sec-11"></a>

## 11. Como Utilizar o Sistema (Guia de Uso)

<a id="sec-11-1"></a>

### 11.1 Tela de Login

Se `$enable_auth = 1`, a primeira página é o formulário de login. Utilize o utilizador e senha definidos na Zona de Controle. Após autenticação bem-sucedida, a sessão PHP mantém o acesso até logout ou expiração da sessão.

<a id="sec-11-2"></a>

### 11.2 Interface Principal

A interface divide-se em:

- **Sidebar (menu lateral):** configuração de IA, ligação a outras bases, upload CSV e lista de perguntas sugeridas.
- **Barra superior:** indicador da fonte ativa, modelo Gemini em uso, seletor de fonte, botão Exportar PDF, Limpar histórico e Sair.
- **Área de chat:** histórico de perguntas e respostas (cada turno é um bloco removível).
- **Caixa de input:** campo para digitar a pergunta em linguagem natural.

<a id="sec-11-3"></a>

### 11.3 Formulando Perguntas

Escreva em português (ou no idioma dos dados) de forma natural. Exemplos de padrões eficazes:

- "Dashboard geral com principais indicadores e distribuição" → vários widgets.
- "Total de registos" / "Média de valor" → KPI.
- "Gráfico de barras: total por país" → barra.
- "Gráfico de pizza por modalidade" → pizza.
- "Listar últimos 10 relatórios" → tabela.
- "Texto com análise dos resultados do docente X" → análise narrativa em HTML.
- "Heatmap mês x ano", "Nuvem de palavras das atividades", "Timeline por data", "Grafo de relações".

O sistema mantém um histórico curto (últimas 3–6 interações) para refinamentos (ex.: "agora em pizza", "filtre só 2024"). Se a nova pergunta for sobre outro assunto, o histórico é ignorado automaticamente. Se zero resultados forem retornados e havia histórico, o sistema refaz a consulta do zero (auto-recovery).

<a id="sec-11-4"></a>

### 11.4 Widgets e Visualizações

| Widget | Quando usar / o que o SQL deve devolver |
| --- | --- |
| `kpi` | 1 valor numérico agregado (`COUNT`, `SUM`, `AVG`) |
| `barra` / `pizza` | 2 colunas: rótulo + valor numérico (`GROUP BY`) |
| `linha` | 2 colunas: período/data + valor |
| `heatmap` | 3 colunas: eixo_x, eixo_y, valor |
| `grafo` | 3 colunas: origem, destino, peso/valor |
| `timeline` | 3 colunas: nome_evento (curto), data_inicio, data_fim |
| `nuvem` | Textos livres; o frontend conta palavras |
| `tabela` | Qualquer `SELECT *` ou lista de colunas |
| `texto` | Análise narrativa; Gemini gera HTML a partir dos dados |

<a id="sec-11-5"></a>

### 11.5 Exportação PDF (Apresentação / Relatório)

Botão **EXPORTAR PAINEL PDF** na barra superior. O utilizador escolhe:

- **Paisagem — Modo Apresentação:** cada pergunta e resultado ocupa uma página A4 horizontal (estilo slides).
- **Retrato — Modo Relatório:** documento A4 vertical contínuo, ideal para impressão.

Gráficos são capturados como imagem (canvas); tabelas e textos são clonados. Blocos removidos pelo utilizador (botão X) não entram no PDF.

<a id="sec-12"></a>

## 12. Segurança (Read-Only)

O sistema foi desenhado para nunca executar comandos de escrita:

1. **Camada PHP** — função `is_sql_safe()`: só permite SQL que comece por `SELECT` ou `WITH`; rejeita `INSERT`, `UPDATE`, `DELETE`, `DROP`, `ALTER`, `TRUNCATE`, `CREATE`, `GRANT`, etc., e também múltiplos statements (`;`).
2. **Camada MySQL** — após conectar, executa `SET SESSION TRANSACTION READ ONLY`.

Mesmo assim, utilize sempre um utilizador de base de dados com privilégios mínimos (apenas `SELECT` nas tabelas necessárias).

<a id="sec-13"></a>

## 13. Fluxo Interno Resumido

1. Utilizador envia pergunta (POST JSON com `prompt` e `source`).
2. PHP resolve a fonte ativa (default / custom / csv) e o schema correspondente.
3. Se houver cache da mesma pergunta, fonte e modelo → reutiliza o plano de widgets.
4. Caso contrário: (opcional) RAG → monta prompt BI → chama Gemini → recebe JSON de widgets (`sql` + `visualizacao` + `titulo` + colunas).
5. Para cada widget: valida SQL → executa (MySQL / SQLite / motor PHP) → se for `texto`, pede narrativa ao Gemini.
6. Se total de linhas = 0 e usou histórico → tenta de novo sem histórico (auto-recovery).
7. Devolve JSON ao frontend → ECharts / tabelas / KPIs são renderizados.

<a id="sec-14"></a>

## 14. Checklist Rápido de Implantação

1. Colocar o ficheiro PHP num servidor com PHP 5.6–8.3+ e extensões necessárias.
2. Preencher `$db_config` (`host`, `user`, `pass`, `name`) se for usar a base padrão.
3. Substituir `$gemini_api_key` pela chave real.
4. Ajustar `$gemini_model` e `$gemini_available_models` aos modelos disponíveis na sua conta.
5. Escrever `$database_schema` descritivo das tabelas reais (ou deixar vazio para auto-extração).
6. Adaptar `$help_examples` ao domínio dos dados.
7. Alterar `$auth_user` e `$auth_pass_md5` (e manter `$enable_auth = 1` em produção).
8. Definir `$base_file_url` se houver links para PDFs/ficheiros.
9. Decidir `$enable_structured_rag` (`0` ou `1`) e as flags `$enable_*`.
10. Testar login → pergunta simples → dashboard → exportação PDF.
11. Confirmar que o utilizador MySQL tem apenas permissões de leitura.

<a id="sec-15"></a>

## 15. Resolução de Problemas Comuns

- **Erro de conexão com o banco.** Verifique host/porta/firewall, credenciais e se o utilizador tem acesso ao schema. Teste com um cliente MySQL externo.
- **Chave Gemini inválida / quota.** Confirme a chave em AI Studio. Verifique quotas e se o modelo listado ainda existe. Experimente outro modelo em `$gemini_available_models`.
- **SQL gerado incorreto.** Melhore `$database_schema` (descrições semânticas). Ative RAG se houver muitas tabelas. Faça perguntas mais específicas.
- **CSV lento ou falha.** Ative `pdo_sqlite`. Para ficheiros enormes, prefira importar para MySQL e usar base customizada.
- **Gráficos não aparecem.** Confirme que o navegador carrega os CDNs do ECharts. Abra a consola do browser para erros JS.
- **Sessão expira / logout inesperado.** Ajuste `session.gc_maxlifetime` no PHP. Em ambientes com balanceamento, configure sticky sessions ou storage de sessão partilhado.
- **PDF exportado vazio ou cortado.** Garanta que há resultados visíveis no painel. Experimente as duas orientações. Gráficos muito altos podem precisar de scroll no modo retrato.

<a id="sec-16"></a>

## 16. Anexo — Localização das Variáveis no Código

Referência rápida das variáveis de configuração (todas no topo do script, antes da lógica de negócio):

| Variável / Bloco | Propósito |
| --- | --- |
| `$enable_auth`, `$auth_user`, `$auth_pass_md5` | Login obrigatório e credenciais |
| `$enable_default_db`, `$enable_custom_db`, `$enable_csv_upload`, `$enable_help_panel` | Ligar/desligar módulos |
| `$pdf_orientation` | Orientação padrão do PDF |
| `$db_config` | Credenciais da base padrão (MySQL) |
| `$base_file_url` | Prefixo de URL para ficheiros/PDFs |
| `$gemini_api_key`, `$gemini_model`, `$gemini_available_models` | API e modelos Gemini |
| `$enable_structured_rag` | Ativar/desativar Schema Pruning |
| `$database_schema` | DDL descritivo enviado ao Gemini |
| `$help_examples` | Perguntas de atalho do menu lateral |

<a id="sec-17"></a>

## 17. Disclaimer e Licença de Uso

### Aviso legal / disclaimer

Este software e o respetivo código-fonte são distribuídos de forma gratuita e aberta, com o objetivo de apoiar análises de dados e o uso de inteligência artificial em contextos institucionais e académicos. O utilizador está autorizado a estudar, adaptar, modificar e melhorar o código conforme as suas necessidades, desde que respeite a licença indicada abaixo.

A utilização deste sistema é feita por conta e risco exclusivo do utilizador. Os desenvolvedores e a instituição de origem não se responsabilizam por quaisquer danos, perdas, resultados incorretos, decisões tomadas com base nas análises geradas, interrupções de serviço, falhas de segurança, custos decorrentes do uso de APIs externas (incluindo a API Google Gemini), nem por qualquer outra consequência direta ou indireta da instalação, configuração ou operação do software.

Antes de utilizar o sistema em ambiente de produção ou para apoiar decisões relevantes, o utilizador deve testar e validar integralmente todas as funcionalidades, confirmar que a estrutura de dados, as consultas geradas e as visualizações atendem aos seus requisitos, e garantir que as configurações de segurança (credenciais, permissões de leitura, chave de API e autenticação) estão corretas e adequadas ao seu contexto.

O software é fornecido "tal como está" (*as is*), sem garantias de qualquer espécie, expressas ou implícitas, incluindo, entre outras, garantias de comercialização, adequação a um fim específico ou ausência de erros. Não há compromisso de suporte técnico contínuo, atualizações ou correção de falhas por parte dos autores.

### Licença de distribuição

Este projeto é disponibilizado sob a licença **MIT License** (Licença MIT), uma licença de software livre e de código aberto amplamente reconhecida, que permite uso, cópia, modificação, fusão, publicação, distribuição e sublicenciamento, com ou sem fins comerciais, desde que o aviso de direitos de autor e o texto da licença sejam mantidos nas cópias ou partes substanciais do software.

Em resumo, a MIT License enquadra-se no modelo de distribuição livre, aberta e gratuita: o código pode ser melhorado e reutilizado com mínima restrição, atribuindo-se a autoria original e isentando os autores de responsabilidade pelo uso que terceiros façam do software.

**Texto de referência da licença MIT (resumo operacional):** o software é fornecido sem garantia; em nenhum caso os autores ou detentores dos direitos de autor serão responsáveis por qualquer reclamação, dano ou outra responsabilidade, seja em ação contratual, ilícito ou de outra forma, decorrente do uso ou de outras relações com o software.

### Licenças de terceiros

Este sistema utiliza bibliotecas, serviços e recursos de terceiros, cada um sujeito às respetivas licenças e termos de uso. O utilizador é responsável por cumprir essas condições. Entre os principais componentes externos destacam-se:

- **Apache ECharts** e **echarts-wordcloud** — visualização de gráficos (licença Apache 2.0).
- **Font Awesome** — ícones na interface (licença própria Font Awesome; uso via CDN conforme termos do fornecedor).
- **html2pdf.js** — geração de PDF no navegador (licença MIT).
- **Google Gemini API** — processamento de linguagem natural e Text-to-SQL (sujeita aos Termos de Serviço e políticas de utilização da Google / Google AI; pode implicar custos e limites de quota associados à chave de API do utilizador).
- **PHP, MySQL/MariaDB, SQLite** e extensões do servidor — conforme as licenças e termos das respetivas plataformas e do ambiente de hospedagem.

A inclusão destas referências não transfere direitos sobre o software de terceiros. Qualquer redistribuição ou uso em produção deve respeitar integralmente as licenças originais de cada componente e os contratos aplicáveis aos serviços externos (em especial a API Gemini).

---

Todas as intervenções necessárias para colocar o sistema em produção estão documentadas nas [secções 3 a 9](#sec-3) e no [checklist da secção 14](#sec-14).

**Sistema desenvolvido por:** Seção de Informática — IAU / USP — [iau.sti@sc.usp.br](mailto:iau.sti@sc.usp.br)
