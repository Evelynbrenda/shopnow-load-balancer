import json
import time
import urllib.request
import urllib.parse
import subprocess
from collections import defaultdict

BASE_URL = "http://localhost:8080"

ALGORITHMS = {
    "round_robin": "ROUND ROBIN",
    "least_connections": "LEAST CONNECTIONS",
    "least_response_time": "LEAST RESPONSE TIME"
}

TOTAL_REQUESTS = 12


def reset_state():
    """Limpa as métricas do balanceador antes de cada algoritmo."""
    subprocess.run(
        [
            "docker", "compose", "exec", "-T",
            "balanceador", "sh", "-c",
            "rm -f /tmp/shopnow_lb_state.json"
        ],
        check=True,
        capture_output=True,
        text=True
    )


for algorithm, title in ALGORITHMS.items():
    print("\n" + "=" * 65)
    print(f" SIMULAÇÃO: {title}")
    print("=" * 65)

    reset_state()
    print("Histórico do balanceador reiniciado.\n")

    distribution = defaultdict(int)
    total_times = defaultdict(float)
    errors = 0

    for request_number in range(1, TOTAL_REQUESTS + 1):
        params = urllib.parse.urlencode({"algorithm": algorithm})
        url = f"{BASE_URL}/?{params}"

        started = time.perf_counter()

        try:
            with urllib.request.urlopen(url, timeout=15) as response:
                data = json.loads(response.read().decode("utf-8"))
                status = response.status

            elapsed = (time.perf_counter() - started) * 1000
            server = data.get("server", "Desconhecido")
            delay = data.get("artificial_delay_ms", 0)

            distribution[server] += 1
            total_times[server] += elapsed

            print(
                f"Requisição {request_number:02d} | "
                f"{server:<12} | "
                f"{elapsed:8.2f} ms | "
                f"Atraso: {delay:3} ms | "
                f"HTTP {status}"
            )

        except Exception as error:
            errors += 1
            print(f"Requisição {request_number:02d} | ERRO: {error}")

        time.sleep(0.2)

    print("\nRESUMO DA DISTRIBUIÇÃO")

    for server in sorted(distribution):
        count = distribution[server]
        average = total_times[server] / count

        print(
            f"{server:<12} | "
            f"{count:02d} requisições | "
            f"Média: {average:.2f} ms"
        )

    print(f"\nTotal de erros: {errors}")

print("\n" + "=" * 65)
print(" SIMULAÇÃO FINALIZADA")
print("=" * 65)
