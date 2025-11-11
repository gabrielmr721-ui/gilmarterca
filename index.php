<?php
require __DIR__ . '/vendor/autoload.php'; // Carrega as dependências instaladas via Composer

$config_error = false; // Flag de controle para erros de configuração
$ia_response = null; // Inicializa a variável que armazenará a resposta da IA
$termo = ''; // Inicializa o termo de entrada

// --- Carregamento das variáveis de ambiente (.env) ---
try {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->safeLoad(); 
} catch (Exception $e) {
    $ia_response = "<div class='erro'>Erro ao carregar o arquivo .env: Verifique se o arquivo existe e as permissões.</div>";
    $config_error = true;
}

// --- Leitura das variáveis de ambiente para API ---
$api_key = $_ENV['CHAVE_API'] ?? null;
$api_url = $_ENV['GROQ_API_URL'] ?? "https://api.groq.com/openai/v1/chat/completions";

// --- Verificação se a chave da API foi carregada ---
if (!$api_key && !$config_error) {
    $ia_response = "<div class='erro'>ERRO DE CONFIGURAÇÃO: A variável CHAVE_API não foi encontrada no arquivo .env.</div>";
    $config_error = true;
}

// --- Processa o formulário quando enviado ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && !$config_error) {

    // Captura o termo enviado e sanitiza para evitar XSS
    $termo = htmlspecialchars($_POST['termo'] ?? '');

    // Cria o prompt a ser enviado para a IA
    $prompt_content = "Explique o termo acadêmico '{$termo}' de forma clara e concisa para um aluno iniciante. Limite a resposta a um parágrafo.";

    // Monta o corpo JSON da requisição
    $payload = json_encode([
        "model" => "llama-3.1-8b-instant",
        "messages" => [
            ["role" => "system", "content" => "Você é um professor prestativo e conciso."],
            ["role" => "user", "content" => $prompt_content]
        ],
        "temperature" => 0.7
    ]);

    // Inicia o cURL e define os parâmetros de conexão
    $ch = curl_init($api_url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "Authorization: Bearer " . $api_key
    ]);

    // Executa a requisição e captura a resposta
    $response = curl_exec($ch);

    // Verifica se ocorreu algum erro no cURL
    if (curl_errno($ch)) {
        $ia_response = "<div class='erro'>Erro cURL: " . curl_error($ch) . "</div>";
    }
    curl_close($ch);

    // Processa o retorno da API se não houve erro de cURL
    if (!$ia_response) {
        $data = json_decode($response, true);

        // Extrai a resposta principal da IA
        if (isset($data['choices'][0]['message']['content'])) {
            $ia_response = $data['choices'][0]['message']['content'];
        } 
        // Exibe mensagem de erro da API, se houver
        elseif (isset($data['error']['message'])) {
            $ia_response = "<div class='erro'>
                Erro da API Groq: " . htmlspecialchars($data['error']['message']) . 
                "<br>Código: " . htmlspecialchars($data['error']['code'] ?? 'N/A') . 
                "<br>Verifique sua chave no arquivo .env!
            </div>";
        }
        // Mostra o JSON completo se a resposta não for reconhecida
        else {
            $ia_response = "<div class='erro'>Erro na Extração da Resposta. JSON de retorno da API:<pre style='background: #fee; border: 1px solid red; padding: 10px; overflow-x: auto;'>" . htmlspecialchars($response) . "</pre></div>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Projeto IA PHP - Explicador de Termos</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
    <h1>Explicador de Termos Acadêmicos (IA + PHP)</h1>

    <!-- Formulário para envio do termo -->
    <form method="post" action="index.php">
        <label for="termo">Digite um Termo Acadêmico para Explicação:</label>
        <input type="text" id="termo" name="termo" required placeholder="Ex: Algoritmo Genético, Paradigma Funcional..." 
                value="<?php echo isset($termo) ? htmlspecialchars($termo) : ''; ?>">
        <button type="submit">Explicar Termo com IA</button>
    </form>

    <!-- Exibição da resposta da IA ou mensagens de erro -->
    <?php
    if ($_SERVER["REQUEST_METHOD"] == "POST" || $config_error) {
        if (isset($ia_response)) {
            echo "<div class='resposta'>";
            
            if ($_SERVER["REQUEST_METHOD"] == "POST" && !$config_error) {
                echo "<h2>Termo: " . htmlspecialchars($termo) . "</h2>";
                echo "<h3>Resultado:</h3>";
            } elseif ($config_error) {
                echo "<h2>Erro de Configuração</h2>";
            }

            if (strpos($ia_response, '<div class=') !== false) {
                echo $ia_response; 
            } else {
                echo "<p>" . nl2br(htmlspecialchars($ia_response)) . "</p>";
            }

            echo "</div>";
        }
    }
    ?>
</body>
</html>
