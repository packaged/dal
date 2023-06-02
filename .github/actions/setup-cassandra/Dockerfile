FROM python:3.8

ENV CQLSH_VERSION=5.0.4
ENV CQLSH_NO_BUNDLED=true
RUN pip install --no-cache-dir cqlsh==${CQLSH_VERSION} cassandra-driver==3.24.0 \
    && echo "export CQLSH_NO_BUNDLED=true" >> ~/.bashrc

COPY entrypoint.sh /entrypoint.sh
COPY cassandra-setup.cql /cassandra-setup.cql

RUN chmod +x /entrypoint.sh
ENTRYPOINT ["/entrypoint.sh"]
