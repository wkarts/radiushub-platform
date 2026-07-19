FROM nginx:1.27-alpine
ARG VCS_REF=""
ARG BUILD_DATE=""
ARG VERSION="1.2.0"
LABEL org.opencontainers.image.title="RadiusHub Web" \
      org.opencontainers.image.version="$VERSION" \
      org.opencontainers.image.revision="$VCS_REF" \
      org.opencontainers.image.created="$BUILD_DATE" \
      org.opencontainers.image.source="https://github.com/wkarts/radiushub-platform"
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY public /var/www/html/public
RUN ln -s /var/www/html/storage/app/public /var/www/html/public/storage
