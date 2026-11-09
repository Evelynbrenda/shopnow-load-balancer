# ShopNow — Load Balancer em PHP

## 1. Sobre o projeto

Projeto acadêmico de Sistemas Distribuídos que implementa um balanceador de carga para uma aplicação fictícia de e-commerce chamada ShopNow.

O balanceador distribui requisições entre três instâncias de servidores, utilizando três algoritmos de distribuição e verificando a disponibilidade das instâncias antes de encaminhar as requisições.

O projeto demonstra conceitos de balanceamento de carga, concorrência, monitoramento de disponibilidade e tolerância a falhas.

## 2. Tecnologias

- PHP 8.3
- Apache
- Docker e Docker Compose
- Python 3
- Apache Benchmark (`ab`)
- SQLite não utilizado neste projeto.

## 3. Arquitetura

A aplicação possui quatro containers:

- **Balanceador:** recebe as requisições na porta 8080.
- **Servidor 1:** instância da aplicação na porta 8081.
- **Servidor 2:** instância da aplicação na porta 8082.
- **Servidor 3:** instância da aplicação na porta 8083.

O balanceador verifica a saúde das instâncias disponíveis e encaminha cada requisição para o servidor selecionado.

### Atrasos artificiais

Para simular servidores com capacidades diferentes, foram configurados atrasos artificiais:

| Servidor | Atraso configurado |
|---|---:|
| Servidor 1 | 30 ms |
| Servidor 2 | 150 ms |
| Servidor 3 | 300 ms |

Esses atrasos permitem observar como os algoritmos se comportam diante de servidores com tempos de resposta diferentes.

## 4. Como executar

Na pasta do projeto, execute:

```bash
docker compose up --build -d
```

### Endereços

- Balanceador: http://localhost:8080
- Servidor 1: http://localhost:8081
- Servidor 2: http://localhost:8082
- Servidor 3: http://localhost:8083

Para verificar os containers:

```bash
docker compose ps
```

## 5. Algoritmos de balanceamento

O projeto implementa três algoritmos:

### 5.1 Round Robin

Distribui as requisições alternadamente entre os servidores saudáveis, mantendo um índice para determinar o próximo servidor.

**Acessar:**

```text
http://localhost:8080/?algorithm=round_robin
```

### 5.2 Least Connections

Seleciona o servidor que possui a menor quantidade de requisições em andamento.

O contador de conexões é incrementado antes do encaminhamento e decrementado quando a requisição termina.

Em caso de empate, utiliza alternância entre os servidores candidatos.

**Acessar:**

```text
http://localhost:8080/?algorithm=least_connections
```

### 5.3 Least Response Time

Seleciona o servidor com o menor tempo médio de resposta registrado.

O balanceador atualiza as médias utilizando uma média móvel exponencial, permitindo que medições recentes influenciem a escolha.

Servidores ainda sem histórico de medição recebem requisições iniciais para que o balanceador possa coletar seus tempos de resposta.

**Acessar:**

```text
http://localhost:8080/?algorithm=least_response_time
```

## 6. Verificação de disponibilidade

Antes de selecionar uma instância, o balanceador realiza uma verificação HTTP dos servidores por meio do Health Check.

A verificação utiliza um endpoint específico que informa se o servidor está online, sem aplicar o atraso artificial.

Caso uma instância esteja indisponível, ela é removida da lista de servidores saudáveis e não participa da seleção.

Se nenhum servidor estiver saudável, o balanceador retorna HTTP 503.

## 7. Simulação de falha

Para simular a indisponibilidade do Servidor 3:

```bash
docker compose stop servidor3
```

Execute a simulação:

```bash
python3 testes/simular_balanceamento.py
```

O balanceador deverá continuar encaminhando as requisições para os servidores disponíveis.

Para restaurar o servidor:

```bash
docker compose start servidor3
```

Durante o teste realizado, o Servidor 3 permaneceu indisponível e os outros dois servidores continuaram atendendo às requisições, sem erros.

## 8. Testes automatizados

Os algoritmos possuem testes automatizados implementados em PHP puro.

Para executar:

```bash
make test
```

**Resultado obtido:**

- 11 testes aprovados.
- 0 testes reprovados.

Os testes verificam o funcionamento do Round Robin, Least Connections e Least Response Time, incluindo situações de empate, ausência de medições e atualização das médias de resposta.

## 9. Simulações de balanceamento

O projeto possui dois scripts em Python para simular o comportamento dos algoritmos.

### 9.1 Simulação sequencial

Arquivo:

```text
testes/simular_balanceamento.py
```

Execução:

```bash
python3 testes/simular_balanceamento.py
```

São realizadas 12 requisições sequenciais para cada algoritmo.

**Resultados obtidos:**

| Algoritmo | Servidor 1 | Servidor 2 | Servidor 3 |
|---|---:|---:|---:|
| Round Robin | 4 | 4 | 4 |
| Least Connections | 4 | 4 | 4 |
| Least Response Time | 10 | 1 | 1 |

O Round Robin distribuiu igualmente as requisições.

O Least Connections também apresentou distribuição equilibrada porque as requisições eram sequenciais e as conexões eram liberadas antes da próxima seleção.

O Least Response Time priorizou o servidor com menor tempo médio de resposta.

### 9.2 Simulação concorrente

Arquivo:

```text
testes/simular_concorrencia.py
```

Execução:

```bash
python3 testes/simular_concorrencia.py
```

Foram realizadas 18 requisições com até 6 trabalhadores simultâneos.

**Resultados obtidos:**

| Algoritmo | Servidor 1 | Servidor 2 | Servidor 3 |
|---|---:|---:|---:|
| Round Robin | 6 | 6 | 6 |
| Least Connections | 12 | 4 | 2 |
| Least Response Time | 9 | 3 | 6 |

A simulação demonstrou comportamentos diferentes:

- **Round Robin:** manteve a distribuição uniforme, sem considerar a velocidade dos servidores.
- **Least Connections:** direcionou mais requisições ao Servidor 1, que conclui suas requisições mais rapidamente e volta a ficar disponível.
- **Least Response Time:** priorizou servidores com melhores tempos médios registrados.

Nas simulações realizadas, todas as requisições foram concluídas com HTTP 200 e sem erros.

## 10. Testes de carga com Apache Benchmark

Os testes de desempenho também foram realizados com Apache Benchmark, utilizando um container Alpine com a ferramenta instalada.

### Parâmetros utilizados

- Total de requisições: 1.000.
- Concorrência: 50 requisições simultâneas.
- Servidores: três instâncias.
- Ferramenta: Apache Benchmark (`ab`).

### Round Robin

```bash
docker run --rm --network shopnow-load-balancer_default alpine:3.20 sh -c "apk add --no-cache apache2-utils >/dev/null && ab -n 1000 -c 50 'http://balanceador/?algorithm=round_robin'"
```

### Least Connections

```bash
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

Os dois algoritmos concluíram as 1.000 requisições sem falhas.

O Least Connections apresentou uma vazão ligeiramente superior, com 94,14 requisições por segundo, enquanto o Round Robin alcançou 93,05 requisições por segundo.

O Round Robin apresentou uma mediana menor de tempo de resposta. Já o Least Connections apresentou um menor tempo máximo de resposta nessa execução.

Os resultados indicam desempenhos próximos. Uma avaliação mais conclusiva exigiria repetir os testes em condições controladas e comparar os resultados de várias execuções.

## 11. Considerações técnicas

O estado compartilhado do balanceador utiliza um arquivo temporário e `flock` para coordenar o acesso entre processos Apache.

O projeto possui finalidade didática e demonstra conceitos de distribuição de carga, concorrência, disponibilidade e tolerância a falhas.

Não substitui um proxy ou balanceador de carga preparado para ambientes de produção.

## 12. Encerrar a aplicação

Para parar e remover os containers:

```bash
docker compose down
```
