# ShopNow — Load Balancer com Nginx

## 1. Sobre o projeto

Projeto acadêmico de Sistemas Distribuídos que implementa um balanceador de carga para uma aplicação fictícia de e-commerce chamada ShopNow.

O balanceador é o **Nginx**, que distribui as requisições entre três instâncias da aplicação usando três algoritmos: **Round Robin**, **Least Connections** e **IP Hash**. Quando uma instância falha, ela é retirada da rotação automaticamente.

O projeto demonstra conceitos de balanceamento de carga, concorrência, monitoramento de disponibilidade e tolerância a falhas.

> A versão anterior, com o balanceador escrito em PHP (incluindo o Least Response Time), está na branch `main`.

## 2. Tecnologias

- Nginx 1.27 (balanceador)
- PHP 8.3 + Apache (instâncias da aplicação)
- Docker e Docker Compose
- Python 3 (simulações)
- Apache Benchmark (`ab`)

## 3. Arquitetura

```text
cliente ──► nginx (:8080) ──► servidor1 (:8081, 30 ms)
                          ├─► servidor2 (:8082, 150 ms)
                          └─► servidor3 (:8083, 300 ms)
```

A aplicação possui quatro containers:

- **Balanceador (Nginx):** recebe as requisições na porta 8080. Toda a configuração está em [`nginx.conf`](nginx.conf).
- **Servidor 1:** instância da aplicação na porta 8081.
- **Servidor 2:** instância da aplicação na porta 8082.
- **Servidor 3:** instância da aplicação na porta 8083.

### Atrasos artificiais

Para simular servidores com capacidades diferentes, foram configurados atrasos artificiais:

| Servidor | Atraso configurado |
|---|---:|
| Servidor 1 | 30 ms |
| Servidor 2 | 150 ms |
| Servidor 3 | 300 ms |

## 4. Como executar

Na pasta do projeto, execute:

```bash
make up
```

### Endereços

- Balanceador: http://localhost:8080
- Servidor 1: http://localhost:8081
- Servidor 2: http://localhost:8082
- Servidor 3: http://localhost:8083

Para verificar os containers:

```bash
make status
```

## 5. Algoritmos de balanceamento

Cada algoritmo é um bloco `upstream` no [`nginx.conf`](nginx.conf). O parâmetro `?algorithm=` da URL escolhe qual upstream será usado (por meio de um `map`). Sem o parâmetro, o padrão é Round Robin. Um valor inválido retorna HTTP 400.

Todos os upstreams usam a diretiva `zone`, que guarda o estado em memória compartilhada. Sem ela, cada processo *worker* do Nginx teria seu próprio contador e o rodízio não seria respeitado entre eles.

### 5.1 Round Robin

É o algoritmo padrão do Nginx (não precisa de diretiva). Distribui as requisições alternadamente entre os servidores disponíveis.

```text
http://localhost:8080/?algorithm=round_robin
```

### 5.2 Least Connections

Diretiva `least_conn`. Envia a requisição para o servidor com menos requisições em andamento. Em caso de empate, usa rodízio entre os empatados.

```text
http://localhost:8080/?algorithm=least_connections
```

### 5.3 IP Hash

Diretiva `ip_hash`. O endereço IP do cliente define o servidor, então o mesmo cliente é sempre atendido pela mesma instância. Isso é útil quando a sessão (ex.: carrinho de compras) fica guardada na memória do servidor.

Para IPv4, o Nginx usa só os **três primeiros octetos** do IP no cálculo. Por isso todos os testes feitos da mesma máquina (ou da mesma rede Docker) caem no mesmo servidor. Esse é o comportamento esperado e demonstra a afinidade de sessão.

```text
http://localhost:8080/?algorithm=ip_hash
```

### Por que não Least Response Time?

O algoritmo `least_time` só existe no **NGINX Plus** (versão paga). A versão open source não mede o tempo de resposta dos servidores para balancear.

## 6. Verificação de disponibilidade

O Nginx open source faz **health check passivo**: ele percebe a falha durante uma requisição real, em vez de consultar os servidores periodicamente (o health check ativo também é exclusivo do NGINX Plus).

Configuração usada em cada servidor:

```nginx
server servidor1 max_fails=1 fail_timeout=10s;
```

- `max_fails=1`: uma falha já marca o servidor como indisponível.
- `fail_timeout=10s`: o servidor fica fora da rotação por 10 segundos e depois volta a ser testado.
- `proxy_next_upstream error timeout http_502 http_503`: a requisição que falhou é reenviada ao próximo servidor, então o cliente não recebe erro.
- `proxy_connect_timeout 2s`: não fica esperando muito tempo um servidor que não responde.

Se todos os servidores estiverem indisponíveis, o Nginx retorna HTTP 502.

## 7. Simulação de falha

Para simular a indisponibilidade do Servidor 3:

```bash
docker compose stop servidor3
```

Execute a simulação:

```bash
python3 testes/simular_balanceamento.py
```

O balanceador continua encaminhando as requisições para os servidores disponíveis. No teste realizado, com o Servidor 3 parado, as requisições foram divididas entre os Servidores 1 e 2, sem erros, tanto no Round Robin quanto no Least Connections. No IP Hash, com o servidor do cliente (Servidor 1) parado, as requisições passaram para outro servidor.

Para restaurar o servidor:

```bash
docker compose start servidor3
```

O Nginx resolve os nomes dos servidores ao iniciar. Se um container for recriado e mudar de IP, reinicie o balanceador com `docker compose restart balanceador`.

## 8. Testes

```bash
make test
```

O comando valida a configuração do Nginx (`nginx -t`) e executa a simulação sequencial.

## 9. Simulações de balanceamento

O projeto possui dois scripts em Python para simular o comportamento dos algoritmos.

### 9.1 Simulação sequencial

```bash
python3 testes/simular_balanceamento.py
```

São realizadas 12 requisições sequenciais para cada algoritmo.

**Resultados obtidos:**

| Algoritmo | Servidor 1 | Servidor 2 | Servidor 3 |
|---|---:|---:|---:|
| Round Robin | 4 | 4 | 4 |
| Least Connections | 4 | 4 | 4 |
| IP Hash | 12 | 0 | 0 |

O Least Connections ficou igual ao Round Robin porque as requisições eram sequenciais: cada uma termina antes da próxima, então todos os servidores ficam sempre com zero conexões (empate). O IP Hash mandou tudo para o mesmo servidor porque todas as requisições vieram do mesmo cliente.

### 9.2 Simulação concorrente

```bash
python3 testes/simular_concorrencia.py
```

Foram realizadas 18 requisições com até 6 trabalhadores simultâneos.

**Resultados obtidos:**

| Algoritmo | Servidor 1 | Servidor 2 | Servidor 3 |
|---|---:|---:|---:|
| Round Robin | 6 | 6 | 6 |
| Least Connections | 12 | 4 | 2 |
| IP Hash | 18 | 0 | 0 |

- **Round Robin:** distribuição uniforme, sem considerar a velocidade dos servidores.
- **Least Connections:** mais requisições para o Servidor 1, que termina rápido e volta a ficar livre.
- **IP Hash:** todas no mesmo servidor (mesmo cliente).

Todas as requisições foram concluídas com HTTP 200 e sem erros.

## 10. Testes de carga com Apache Benchmark

Os testes de desempenho foram realizados com Apache Benchmark, usando um container Alpine com a ferramenta instalada.

### Parâmetros utilizados

- Total de requisições: 1.000.
- Concorrência: 50 requisições simultâneas.
- Servidores: três instâncias.

### Comandos

```bash
docker run --rm --network shopnow-load-balancer_default alpine:3.20 sh -c "apk add --no-cache apache2-utils >/dev/null && ab -n 1000 -c 50 'http://balanceador/?algorithm=round_robin'"
docker run --rm --network shopnow-load-balancer_default alpine:3.20 sh -c "apk add --no-cache apache2-utils >/dev/null && ab -n 1000 -c 50 'http://balanceador/?algorithm=least_connections'"
docker run --rm --network shopnow-load-balancer_default alpine:3.20 sh -c "apk add --no-cache apache2-utils >/dev/null && ab -n 1000 -c 50 'http://balanceador/?algorithm=ip_hash'"
```

### Resultados obtidos

| Métrica | Round Robin | Least Connections | IP Hash |
|---|---:|---:|---:|
| Requisições concluídas | 1.000 | 1.000 | 1.000 |
| Falhas reais (Connect / Receive / Exceptions / não-2xx) | 0 | 0 | 0 |
| "Failed requests" do ab (todas do tipo `Length`) | 648 | 678 | 0 |
| Tempo total | 5,892 s | 2,685 s | 2,483 s |
| Requisições por segundo | 169,72 | 372,50 | 402,76 |
| Tempo médio por requisição | 294,605 ms | 134,227 ms | 124,145 ms |
| Mediana | 152 ms | 63 ms | 124 ms |
| 95% das requisições em até | 1.050 ms | 303 ms | 155 ms |
| Maior tempo | 1.209 ms | 455 ms | 163 ms |

### Sobre o "Failed requests"

O `ab` usa o tamanho da primeira resposta como referência e conta como falha qualquer resposta de tamanho diferente. O Servidor 1 responde `"artificial_delay_ms": 30` (161 bytes) e os Servidores 2 e 3 respondem `150` e `300` (162 bytes). Então essas "falhas" são só respostas de servidores diferentes do primeiro, todas com HTTP 200. Isso acaba mostrando a distribuição:

- **Round Robin:** a primeira resposta veio do Servidor 1 (161 bytes). As 648 "falhas" são as respostas dos Servidores 2 e 3, cerca de 2/3 do total.
- **Least Connections:** a primeira resposta veio do Servidor 2 ou 3 (162 bytes). As 678 "falhas" são as respostas do Servidor 1, ou seja, cerca de 68% foi para o servidor mais rápido.
- **IP Hash:** 0 "falhas", porque todas as respostas vieram do mesmo servidor (o Servidor 1).

### Análise dos resultados

- O **Least Connections** teve mais que o dobro de requisições por segundo do Round Robin e mediana 89 ms menor. O Round Robin manda 1/3 das requisições para o Servidor 3 (300 ms) mesmo quando ele já está sobrecarregado, o que faz o 95º percentil passar de 1 segundo.
- O **IP Hash** teve o melhor número apenas porque o hash do cliente de teste caiu no Servidor 1, o mais rápido. Com outro IP poderia cair no Servidor 3 e ser o pior resultado. Ele não serve para comparar desempenho, e sim para garantir afinidade de sessão.

Os números variam entre execuções, então o mais confiável é a comparação feita no mesmo momento e na mesma máquina, como nesta tabela.

## 11. Encerrar a aplicação

```bash
make down
```
