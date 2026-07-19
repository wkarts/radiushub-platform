FROM freeradius/freeradius-server:3.2.10
ARG DEBIAN_FRONTEND=noninteractive
ARG VCS_REF=""
ARG BUILD_DATE=""
ARG VERSION="1.3.3"
LABEL org.opencontainers.image.title="RadiusHub FreeRADIUS" \
      org.opencontainers.image.version="$VERSION" \
      org.opencontainers.image.revision="$VCS_REF" \
      org.opencontainers.image.created="$BUILD_DATE" \
      org.opencontainers.image.source="https://github.com/wkarts/radiushub-platform"
USER root
RUN apt-get update && apt-get install -y --no-install-recommends \
    freeradius-postgresql freeradius-mysql postgresql-client default-mysql-client ca-certificates \
    && rm -rf /var/lib/apt/lists/*
COPY docker/freeradius/entrypoint.sh /usr/local/bin/radiushub-radius-entrypoint
COPY resources/freeradius /opt/radiushub-radius/templates
RUN chmod +x /usr/local/bin/radiushub-radius-entrypoint
ENTRYPOINT ["radiushub-radius-entrypoint"]
CMD ["freeradius", "-f"]
