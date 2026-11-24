<?php
declare(strict_types=1);

$baseUrl = rtrim($argv[1] ?? 'http://localhost:8000', '/');
$failures = 0;
$tests = 0;
$createdId = null;

function request(string $baseUrl, string $method, string $path, ?array $body = null): array
{
    $headers = [];
    $options = [
        'http' => [
            'method' => $method,
            'ignore_errors' => true,
            'header' => "Accept: application/json\r\n",
        ],
    ];

    if ($body !== null) {
        $payload = json_encode($body, JSON_UNESCAPED_UNICODE);
        $options['http']['header'] .= "Content-Type: application/json\r\n";
        $options['http']['content'] = $payload;
    }

    $response = file_get_contents(
        $baseUrl . $path,
        false,
        stream_context_create($options)
    );

    if ($response === false) {
        throw new RuntimeException('Não foi possível conectar à API em ' . $baseUrl);
    }

    $status = 0;
    foreach ($http_response_header ?? [] as $header) {
        if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $header, $matches)) {
            $status = (int) $matches[1];
        }
    }

    $data = json_decode($response, true);
    if (!is_array($data)) {
        $data = ['resposta' => $response];
    }

    return ['status' => $status, 'body' => $data];
}

function testCase(string $nome, callable $teste): void
{
    global $failures, $tests;
    $tests++;

    try {
        $resultado = $teste();
        if ($resultado === true) {
            echo "OK   - {$nome}\n";
            return;
        }

        $failures++;
        echo "FALHA - {$nome}: " . (string) $resultado . "\n";
    } catch (Throwable $erro) {
        $failures++;
        echo "ERRO - {$nome}: " . $erro->getMessage() . "\n";
    }
}

echo "Testando a API em {$baseUrl}\n\n";

testCase('POST /situacao cadastra um registro', function () use ($baseUrl, &$createdId): bool|string {
    $resposta = request($baseUrl, 'POST', '/situacao', [
        'nome' => 'Aluno Teste',
        'unidadeCurricular' => 'Desenvolvimento Web',
        'aprovado' => true,
    ]);

    if ($resposta['status'] !== 201) {
        return "esperado status 201, recebido {$resposta['status']}";
    }
    if (($resposta['body']['nome'] ?? null) !== 'Aluno Teste') {
        return 'nome não foi retornado corretamente';
    }
    if (!is_int($resposta['body']['id'] ?? null)) {
        return 'id não foi retornado';
    }

    $createdId = $resposta['body']['id'];
    return true;
});

testCase('GET /situacao/{id} consulta o registro criado', function () use ($baseUrl, &$createdId): bool|string {
    if ($createdId === null) {
        return 'não existe id criado';
    }

    $resposta = request($baseUrl, 'GET', '/situacao/' . $createdId);
    if ($resposta['status'] !== 200) {
        return "esperado status 200, recebido {$resposta['status']}";
    }
    if (($resposta['body']['aprovado'] ?? null) !== true) {
        return 'aprovado deveria ser true';
    }

    return true;
});

testCase('PUT /situacao/{id} atualiza aprovado', function () use ($baseUrl, &$createdId): bool|string {
    if ($createdId === null) {
        return 'não existe id criado';
    }

    $resposta = request($baseUrl, 'PUT', '/situacao/' . $createdId, [
        'aprovado' => false,
    ]);
    if ($resposta['status'] !== 200) {
        return "esperado status 200, recebido {$resposta['status']}";
    }

    $consulta = request($baseUrl, 'GET', '/situacao/' . $createdId);
    if (($consulta['body']['aprovado'] ?? null) !== false) {
        return 'aprovado não foi atualizado';
    }

    return true;
});

testCase('POST /situacao rejeita dados inválidos', function () use ($baseUrl): bool|string {
    $resposta = request($baseUrl, 'POST', '/situacao', [
        'nome' => '',
        'unidadeCurricular' => '',
        'aprovado' => true,
    ]);

    if ($resposta['status'] !== 400) {
        return "esperado status 400, recebido {$resposta['status']}";
    }

    return true;
});

testCase('PUT /situacao/{id} rejeita campos extras', function () use ($baseUrl, &$createdId): bool|string {
    if ($createdId === null) {
        return 'não existe id criado';
    }

    $resposta = request($baseUrl, 'PUT', '/situacao/' . $createdId, [
        'aprovado' => true,
        'nome' => 'Tentativa inválida',
    ]);

    if ($resposta['status'] !== 400) {
        return "esperado status 400, recebido {$resposta['status']}";
    }

    return true;
});

testCase('DELETE /situacao/{id} remove o registro', function () use ($baseUrl, &$createdId): bool|string {
    if ($createdId === null) {
        return 'não existe id criado';
    }

    $resposta = request($baseUrl, 'DELETE', '/situacao/' . $createdId);
    if ($resposta['status'] !== 200) {
        return "esperado status 200, recebido {$resposta['status']}";
    }

    return true;
});

testCase('GET /situacao/{id} retorna 404 após remoção', function () use ($baseUrl, &$createdId): bool|string {
    if ($createdId === null) {
        return 'não existe id criado';
    }

    $resposta = request($baseUrl, 'GET', '/situacao/' . $createdId);
    if ($resposta['status'] !== 404) {
        return "esperado status 404, recebido {$resposta['status']}";
    }

    return true;
});

echo "\nResultado: " . ($tests - $failures) . "/{$tests} testes passaram.\n";
exit($failures === 0 ? 0 : 1);
