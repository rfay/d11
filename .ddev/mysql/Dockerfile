# ============================================================================
# Stage 1: Builder - Install packages and collect dependencies
# ============================================================================
FROM debian:bookworm-slim AS builder

# Install all required packages via apt
RUN apt-get update && \
    DEBIAN_FRONTEND=noninteractive apt-get install -y \
    bzip2 \
    curl \
    less \
    pv \
    wget \
    zstd \
    ca-certificates \
    file \
    && rm -rf /var/lib/apt/lists/*

# Create staging directory structure matching target filesystem
RUN mkdir -p /staging/usr/bin \
             /staging/usr/lib \
             /staging/lib/x86_64-linux-gnu \
             /staging/lib64 \
             /staging/etc

# Copy helper script for dependency resolution
COPY copy-with-deps.sh /usr/local/bin/
RUN chmod 755 /usr/local/bin/copy-with-deps.sh

# Copy each binary and its dependencies to staging
RUN /usr/local/bin/copy-with-deps.sh pv /staging
RUN /usr/local/bin/copy-with-deps.sh zstd /staging
RUN /usr/local/bin/copy-with-deps.sh unzstd /staging
RUN /usr/local/bin/copy-with-deps.sh bzip2 /staging
RUN /usr/local/bin/copy-with-deps.sh bunzip2 /staging
RUN /usr/local/bin/copy-with-deps.sh curl /staging
RUN /usr/local/bin/copy-with-deps.sh wget /staging
RUN /usr/local/bin/copy-with-deps.sh less /staging

# Add custom healthcheck script
COPY healthcheck.sh /staging/healthcheck.sh
RUN chmod 755 /staging/healthcheck.sh

# ============================================================================
# Stage 2: Hardened MySQL - Copy staged files
# ============================================================================
FROM dhi.io/mysql:8.4

# Copy binaries only
# Note: Some binaries may not work if they need libraries not in the hardened image
COPY --from=builder /staging/usr/bin/* /usr/bin/

# Copy healthcheck script
COPY --from=builder /staging/healthcheck.sh /healthcheck.sh

# Switch to mysql user (hardened image enforces this)
USER mysql

# Set working directory
WORKDIR /var/lib/mysql

# Expose MySQL port
EXPOSE 3306

# Healthcheck for DDEV compatibility
HEALTHCHECK --interval=2s --retries=30 --start-period=120s --timeout=10s \
    CMD ["/healthcheck.sh"]

# Let the base image handle CMD/ENTRYPOINT
