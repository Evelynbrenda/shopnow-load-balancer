# ShopNow — Load Balancer em PHP

## 1. Sobre o projeto

Projeto acadêmico de Sistemas Distribuídos que implementa um balanceador de carga para uma aplicação de e-commerce fictícia chamada ShopNow.

O balanceador distribui requisições entre três instâncias de servidores, utilizando dois algoritmos de distribuição e verificando a disponibilidade dos servidores antes de encaminhar as requisições.

## 2. Tecnologias

- PHP 8.3
- Apache
- Docker e Docker Compose
- Apache Benchmark (`ab`)
- PowerShell

## 3. Arquitetura

A aplicação possui quatro containers:

- **Balanceador:** recebe as requisições na porta 8080.
- **Servidor 1:** instância da aplicação na porta 8081.
- **Servidor 2:** instância da aplicação na porta 8082.
- **Servidor 3:** instância da aplicação na porta 8083.

O balanceador verifica a saúde das instâncias disponíveis e encaminha cada requisição para o servidor selecionado.

## 4. Como executar

Na pasta do projeto, execute:

```powershell
docker compose up --build -d
```

### Endereços

- Balanceador: http://localhost:8080
- Servidor 1: http://localhost:8081
- Servidor 2: http://localhost:8082
- Servidor 3: http://localhost:8083

## 5. Algoritmos de balanceamento

O projeto implementa dois algoritmos:

### Round Robin

Distribui as requisições alternadamente entre os servidores saudáveis, mantendo um índice para determinar o próximo servidor.

**Acessar:**

http://localhost:8080/?algorithm=round_robin

### Least Connections

Seleciona o servidor que possui a menor quantidade de requisições em andamento.

O contador de conexões é incrementado antes do encaminhamento e decrementado quando a requisição termina.

**Acessar:**

http://localhost:8080/?algorithm=least_connections

## 6. Verificação de disponibilidade

Antes de selecionar uma instância, o balanceador realiza uma verificação HTTP dos servidores.

Caso uma instância esteja indisponível, ela não participa da seleção.

Se nenhum servidor estiver saudável, o balanceador retorna HTTP 503.

## 7. Simulação de falha

Para simular a indisponibilidade de um servidor, execute:

```powershell
docker compose stop servidor2
```

Realize requisições pelos dois algoritmos:

```powershell
curl.exe "http://localhost:8080/?algorithm=round_robin"
curl.exe "http://localhost:8080/?algorithm=least_connections"
```

Depois, restaure o servidor:

```powershell
docker compose start servidor2
```

Durante o teste realizado, o balanceador continuou atendendo às requisições por meio das outras duas instâncias.

## 8. Testes automatizados

Os algoritmos possuem testes automatizados implementados em PHP puro.

Para executar:

```powershell
docker run --rm -v "${PWD}:/app" -w /app php:8.3-cli php testes/test_algoritmos.php
```

**Resultado obtido:**

- 6 testes aprovados.
- 0 testes reprovados.

Os testes verificam a seleção de servidores pelo Round Robin e pelo Least Connections, incluindo situações de empate e contadores não inicializados.

## 9. Testes de carga

Os testes de desempenho foram realizados com Apache Benchmark, utilizando um container Alpine com Apache Benchmark instalado.

### Parâmetros utilizados

- Total de requisições: 1.000.
- Concorrência: 50 requisições simultâneas.
- Servidores: três instâncias.
- Ferramenta: Apache Benchmark (`ab`).

### Comando para Round Robin

```powershell
docker run --rm --network shopnow-load-balancer_default alpine:3.20 sh -c "apk add --no-cache apache2-utils >/dev/null && ab -n 1000 -c 50 'http://balanceador/?algorithm=round_robin'"
```

### Comando para Least Connections

```powershell
docker run --rm --network shopnow-load-balancer_default alpine:3.20 sh -c "apk add --no-cache apache2-utils >/dev/null && ab -n 1000 -c 50 'http://balanceador/?algorithm=least_connections'"
```

### Resultados obtidos

| Métrica | Round Robin | Least Connections |
|---|---:|---:|
| Requisições concluídas | 1.000 | 1.000 |
| Falhas | 0 | 0 |
| Tempo total | 10,747 s | 10,623 s |
| Requisições por segundo | 93,05 | 94,14 |
| Tempo médio por requisição | 537,355 ms | 531,129 ms |
| Mediana | 359 ms | 455 ms |
| Maior tempo | 4.597 ms | 1.392 ms |

### Análise dos resultados

Os dois algoritmos conseguiram concluir as 1.000 requisições sem falhas.

O Least Connections apresentou uma vazão ligeiramente superior, com 94,14 requisições por segundo, enquanto o Round Robin alcançou 93,05 requisições por segundo.

O Round Robin apresentou uma mediana menor de tempo de resposta. Já o Least Connections apresentou um menor tempo máximo de resposta nessa execução.

Os resultados indicam desempenhos próximos. Uma avaliação mais conclusiva exigiria repetir os testes em condições controladas e comparar os resultados de várias execuções.

## 10. Considerações técnicas

O estado compartilhado do balanceador utiliza um arquivo temporário e `flock` para coordenar o acesso entre processos Apache.

O projeto possui finalidade didática e demonstra conceitos de distribuição de carga, concorrência, disponibilidade e tolerância a falhas. Não substitui um proxy ou balanceador de carga preparado para ambientes de produção.

## 11. Encerrar a aplicação

Para parar e remover os containers, execute:

```powershell
docker compose down
```