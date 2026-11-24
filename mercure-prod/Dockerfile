FROM dunglas/mercure:v1.1.5

COPY Caddyfile /etc/caddy/Caddyfile
COPY entrypoint.sh /entrypoint.sh
RUN sed -i 's/\r$//' /entrypoint.sh && chmod +x /entrypoint.sh

EXPOSE 80

HEALTHCHECK --interval=15s --timeout=5s --start-period=30s --retries=5 CMD ["wget", "-q", "--spider", "http://127.0.0.1:2019/mercure/health/ready"]

ENTRYPOINT ["/entrypoint.sh"]
