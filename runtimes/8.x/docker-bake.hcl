variable "APP_DIR" {
    default = "."
}

variable "RUNTIME_DIR" {
    default = "./vendor/reyemtech/sail/runtimes/8.x"
}

variable "CERTS_DIR" {
    default = "${RUNTIME_DIR}/certs"
}

variable "PHP_VERSION" {
    default = "8.4"
}

variable "ALPINE_VERSION" {
    default = "3.21"
}

variable "PUSH" {
    default = true
}

variable "REGISTRY" {
    default = "ghcr.io"
}

variable "ORG" {
    default = "your-org"
}

variable "APP_NAME" {
    default = "your-app"
}

variable "VERSION" {
    default = "1.0.0"
}

variable "ARCHS" {
    default = "linux/amd64,linux/arm64"
}

variable "REMOVE_NODE_MODULES" {
    default = "true"
}

# Frontend build-time configuration, forwarded to Dockerfile.app-build so bundlers
# can inline it during `npm run build`. All default to empty so apps that do not
# use Sentry are unaffected. The auth token is passed as a secret, not a variable.
variable "VITE_SENTRY_DSN" {
    default = ""
}

variable "VITE_SENTRY_RELEASE" {
    default = ""
}

variable "SENTRY_ORG" {
    default = ""
}

variable "SENTRY_PROJECT" {
    default = ""
}

group "default" {
    targets = ["app", "production-cli", "production-fpm"]
}

target "base" {
    name = "base-${tgt}"
    context = "${RUNTIME_DIR}"
    contexts = {
        "runtime" = "${RUNTIME_DIR}"
        "certs" = "${CERTS_DIR}"
    }
    matrix = {
        tgt = ["cli", "fpm"]
    }
    platforms = PUSH ? split(",", ARCHS) : split(",", ARCHS)
    dockerfile= "Dockerfile.base"
    args = {
        ALPINE_VERSION = "${ALPINE_VERSION}"
        BASE_IMAGE = "php:${PHP_VERSION}-${tgt}-alpine"
        PHP_VERSION = "${PHP_VERSION}"
        PHP_VERSION_NUM = replace("${PHP_VERSION}", ".", "")
    }
}

target "app-build" {
    name = "app-build-${tgt}"
    context = "${RUNTIME_DIR}"
    contexts = {
        "base" = "target:base-${tgt}"
        "app" = "${APP_DIR}"
        "runtime" = "${RUNTIME_DIR}"
        "package" = "${RUNTIME_DIR}/../../"
    }
    dockerfile= "Dockerfile.app-build"
    platforms = PUSH ? split(",", ARCHS) : split(",", ARCHS)
    matrix = {
        tgt = ["cli", "fpm"]
    }
    args = {
        REMOVE_NODE_MODULES = "${REMOVE_NODE_MODULES}"
        VITE_SENTRY_DSN = "${VITE_SENTRY_DSN}"
        VITE_SENTRY_RELEASE = "${VITE_SENTRY_RELEASE}"
        SENTRY_ORG = "${SENTRY_ORG}"
        SENTRY_PROJECT = "${SENTRY_PROJECT}"
    }
    # Optional; when SENTRY_AUTH_TOKEN is unset BuildKit supplies no secret and the
    # build behaves exactly as before.
    secret = [
        "type=env,id=sentry_auth_token,env=SENTRY_AUTH_TOKEN",
    ]
}

target "production" {
    name = "production-${tgt}"
    context = "${RUNTIME_DIR}"
    contexts = {
        "base" = "target:base-${tgt}"
        "app-build" = "target:app-build-${tgt}"
        "runtime" = "${RUNTIME_DIR}"
    }
    dockerfile= "Dockerfile.production"
    matrix = {
        tgt = ["cli", "fpm"]
    }
    output = [{
        type = PUSH == true ? "registry" : "docker"
    }]
    labels = {
        "maintainer" = "Reyem Tech"
        "version" = "${VERSION}"
        "description" = "Production image for ${APP_NAME} (${tgt == "cli" ? "worker" : "web"})"
        "org.opencontainers.image.created" = "${timestamp()}"
        "org.opencontainers.image.authors" = "Reyem Tech"
        "org.opencontainers.image.url" = "https://reyem.tech"
        "org.opencontainers.image.documentation" = "https://github.com/reyemtech/sail"
        "org.opencontainers.image.source" = "https://github.com/reyemtech/sail"
        "org.opencontainers.image.version" = "${VERSION}"
        "org.opencontainers.image.vendor" = "Reyem Tech"
        "org.opencontainers.image.licenses" = "MIT"
        "org.opencontainers.image.title" = "${APP_NAME}-${tgt == "cli" ? "worker" : "web"}"
        "org.opencontainers.image.description" = "Production image for ${APP_NAME} (${tgt == "cli" ? "worker" : "web"})"
    }
    platforms = PUSH ? split(",", ARCHS) : split(",", ARCHS)
    tags = [
        "${REGISTRY}/${ORG}/${APP_NAME}-${tgt == "cli" ? "worker" : "web"}:${VERSION}",
        "${REGISTRY}/${ORG}/${APP_NAME}-${tgt == "cli" ? "worker" : "web"}:latest",
    ]
}

target "app" {
    context = "${RUNTIME_DIR}"
    contexts = {
        "base" = "target:base-fpm"
        "runtime" = "${RUNTIME_DIR}"
    }
    dockerfile= "Dockerfile.app"
    output = [{
        type = "docker"
    }]
    tags = [
        "${ORG}/${APP_NAME}:${VERSION}",
        "${ORG}/${APP_NAME}:latest",
    ]
    labels = {
        "maintainer" = "Reyem Tech"
        "version" = "${VERSION}"
        "description" = "Local image for ${APP_NAME}"
        "org.opencontainers.image.created" = "${timestamp()}"
        "org.opencontainers.image.authors" = "Reyem Tech"
        "org.opencontainers.image.url" = "https://reyem.tech"
        "org.opencontainers.image.documentation" = "https://github.com/reyemtech/sail"
        "org.opencontainers.image.source" = "https://github.com/reyemtech/sail"
        "org.opencontainers.image.version" = "${VERSION}"
        "org.opencontainers.image.vendor" = "Reyem Tech"
        "org.opencontainers.image.licenses" = "MIT"
        "org.opencontainers.image.title" = "${APP_NAME}"
        "org.opencontainers.image.description" = "Local image for ${APP_NAME}"
    }
}
