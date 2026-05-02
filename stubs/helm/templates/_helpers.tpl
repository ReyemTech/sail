{{/*
Expand the name of the chart.
Works with both .Values.name (standard context) and .main.name (_spec template context).
Uses .main.name if in _spec context, otherwise .Values.name, with default "website".
Falls back to standard Helm naming if neither is available.
*/}}
{{- define "sail.name" -}}
{{- $name := "" }}
{{- if and .main .main.name }}
{{- $name = .main.name | default "website" | lower }}
{{- else if .Values.name }}
{{- $name = .Values.name | default "website" | lower }}
{{- else if .Chart }}
{{- $name = default .Chart.Name .Values.nameOverride }}
{{- else }}
{{- $name = "website" }}
{{- end }}
{{- $name | trunc 63 | trimSuffix "-" }}
{{- end }}

{{/*
Create a default fully qualified app name.
Uses .Values.name if provided, otherwise falls back to standard Helm naming.
*/}}
{{- define "sail.fullname" -}}
{{- if .Values.fullnameOverride }}
{{- .Values.fullnameOverride | trunc 63 | trimSuffix "-" }}
{{- else if .Values.name }}
{{- .Values.name | lower | trunc 63 | trimSuffix "-" }}
{{- else if and .Chart .Release }}
{{- $name := default .Chart.Name .Values.nameOverride }}
{{- if contains $name .Release.Name }}
{{- .Release.Name | trunc 63 | trimSuffix "-" }}
{{- else }}
{{- printf "%s-%s" .Release.Name $name | trunc 63 | trimSuffix "-" }}
{{- end }}
{{- else }}
{{- include "sail.name" . }}
{{- end }}
{{- end }}

{{/*
Detect the ExternalSecret API version available in the cluster.
Returns "external-secrets.io/v1" if available, otherwise falls back to "external-secrets.io/v1beta1".
*/}}
{{- define "sail.externalSecret.apiVersion" -}}
{{- if .Capabilities.APIVersions.Has "external-secrets.io/v1" -}}
external-secrets.io/v1
{{- else if .Capabilities.APIVersions.Has "external-secrets.io/v1beta1" -}}
external-secrets.io/v1beta1
{{- else -}}
external-secrets.io/v1beta1
{{- end -}}
{{- end }}

{{/*
Standard Kubernetes labels following best practices.
Includes selector labels plus additional metadata labels.
Note: app.kubernetes.io/instance is included here for pod identification
but NOT in selectorLabels to keep selectors immutable.
*/}}
{{- define "sail.labels" -}}
{{- if .Chart }}
helm.sh/chart: {{ include "sail.chart" . }}
{{- end }}
{{ include "sail.selectorLabels" . }}
{{- if .Release }}
app.kubernetes.io/instance: {{ .Release.Name }}
{{- end }}
{{- if .Chart.AppVersion }}
app.kubernetes.io/version: {{ .Chart.AppVersion | quote }}
{{- end }}
{{- if .Release }}
app.kubernetes.io/managed-by: {{ .Release.Service }}
{{- end }}
{{- end }}

{{/*
Selector labels used by deployments, services, etc.
NOTE: These labels must be immutable. We include app.kubernetes.io/instance
for backward compatibility with existing deployments. If you need to change
the release name, you will need to delete and recreate the Deployment.
*/}}
{{- define "sail.selectorLabels" -}}
app.kubernetes.io/name: {{ include "sail.name" . }}
{{- if .Release }}
app.kubernetes.io/instance: {{ .Release.Name }}
{{- end }}
{{- end }}

{{/*
Chart name and version as used by the chart label.
*/}}
{{- define "sail.chart" -}}
{{- if and .Chart .Chart.Name .Chart.Version }}
{{- printf "%s-%s" .Chart.Name .Chart.Version | replace "+" "_" | trunc 63 | trimSuffix "-" }}
{{- else }}
{{- "chart-unknown" }}
{{- end }}
{{- end }}

{{/*
Create the name of the service account to use
*/}}
{{- define "sail.serviceAccountName" -}}
{{- if .Values.serviceAccount.create }}
{{- default (include "sail.fullname" .) .Values.serviceAccount.name }}
{{- else }}
{{- default "default" .Values.serviceAccount.name }}
{{- end }}
{{- end }}

{{/*
Render the container resources block.
Fallback order:
  1. tier-scoped .resources (from $scoped in deployment-*.stub)
  2. top-level .main.resources
  3. hardcoded defaults (cpu 100m/2000m, memory 256Mi/1Gi)

Ephemeral-storage is deliberately omitted — spec 1's LimitRange
injects 500Mi/2Gi at the namespace level.

Input:
  .tier     — tier-specific resources (may be nil/empty)
  .fallback — top-level fallback resources (may be nil/empty)
*/}}
{{- define "sail.resources" -}}
{{- $tier := .tier | default dict -}}
{{- $fallback := .fallback | default dict -}}
{{- if $tier }}
{{- toYaml $tier -}}
{{- else if $fallback }}
{{- toYaml $fallback -}}
{{- else }}
requests:
  cpu: 100m
  memory: 256Mi
limits:
  cpu: "2"
  memory: 1Gi
{{- end }}
{{- end -}}

{{/*
Render the env: entries shared by every Laravel pod (web, worker, scheduler).
Wires SAIL_LOG_, DB_, REDIS_, REDIS_USE_SENTINEL, and AWS_/FILESYSTEM_DISK from
k8s secrets and values. Detects context the same way sail.name does — uses
.main.* if invoked from sail.laravelSpec, otherwise .Values.* at the chart root.

REDIS_USE_SENTINEL is the consumer-app feature flag for the sentinel-aware
Redis client (Laravel\Sail\Redis\PhpRedisSentinelConnector). Defaults to false
so existing deployments keep their current direct-master connection until they
opt in via `redis.useSentinel: true` in values.yaml.
*/}}
{{- define "sail.laravelEnv" -}}
{{- $database := dict -}}
{{- $redis := dict -}}
{{- $s3 := dict -}}
{{- $logging := dict -}}
{{- if .main -}}
{{- $database = .main.database | default dict -}}
{{- $redis = .main.redis | default dict -}}
{{- $s3 = .main.s3 | default dict -}}
{{- $logging = .main.logging | default dict -}}
{{- else -}}
{{- $database = .Values.database | default dict -}}
{{- $redis = .Values.redis | default dict -}}
{{- $s3 = .Values.s3 | default dict -}}
{{- $logging = .Values.logging | default dict -}}
{{- end }}
- name: SAIL_LOG_MODE
  value: {{ $logging.mode | default "both" | quote }}
- name: SAIL_LOG_MAX_ARCHIVES
  value: {{ $logging.maxArchives | default 20 | int64 | toString | quote }}
- name: SAIL_LOG_ROTATE_SIZE
  value: {{ $logging.maxFileSize | default 10000000 | int64 | toString | quote }}
{{- if $database.secret }}
- name: DB_CONNECTION
  value: {{ $database.connection | default "mysql" }}
- name: DB_HOST
  valueFrom:
    secretKeyRef:
      name: {{ $database.secret }}
      key: host
- name: DB_PORT
  valueFrom:
    secretKeyRef:
      name: {{ $database.secret }}
      key: port
- name: DB_DATABASE
  valueFrom:
    secretKeyRef:
      name: {{ $database.secret }}
      key: database
- name: DB_USERNAME
  valueFrom:
    secretKeyRef:
      name: {{ $database.secret }}
      key: username
- name: DB_PASSWORD
  valueFrom:
    secretKeyRef:
      name: {{ $database.secret }}
      key: password
{{- end }}
{{- if $redis.secret }}
- name: REDIS_HOST
  valueFrom:
    secretKeyRef:
      name: {{ $redis.secret }}
      key: host
- name: REDIS_PORT
  valueFrom:
    secretKeyRef:
      name: {{ $redis.secret }}
      key: port
- name: REDIS_CLIENT
  value: phpredis
- name: REDIS_PASSWORD
  valueFrom:
    secretKeyRef:
      name: {{ $redis.secret }}
      key: password
- name: REDIS_SENTINEL_HOST
  valueFrom:
    secretKeyRef:
      name: {{ $redis.secret }}
      key: sentinel_host
- name: REDIS_SENTINEL_PORT
  valueFrom:
    secretKeyRef:
      name: {{ $redis.secret }}
      key: sentinel_port
- name: REDIS_SENTINEL_SERVICE
  valueFrom:
    secretKeyRef:
      name: {{ $redis.secret }}
      key: sentinel_service
- name: REDIS_USE_SENTINEL
  value: {{ ($redis.useSentinel | default false) | quote }}
{{- end }}
{{- if $s3.secret }}
- name: AWS_ACCESS_KEY_ID
  valueFrom:
    secretKeyRef:
      name: {{ $s3.secret }}
      key: accessKeyId
- name: AWS_SECRET_ACCESS_KEY
  valueFrom:
    secretKeyRef:
      name: {{ $s3.secret }}
      key: secretAccessKey
- name: AWS_BUCKET
  valueFrom:
    secretKeyRef:
      name: {{ $s3.secret }}
      key: bucket
- name: AWS_ENDPOINT
  valueFrom:
    secretKeyRef:
      name: {{ $s3.secret }}
      key: endpoint
- name: AWS_DEFAULT_REGION
  value: {{ $s3.region | default "us-east-1" }}
- name: AWS_USE_PATH_STYLE_ENDPOINT
  value: {{ $s3.pathStyle | default "true" | quote }}
- name: FILESYSTEM_DISK
  value: s3
{{- if $s3.url }}
- name: AWS_URL
  value: {{ $s3.url }}
{{- end }}
{{- end }}
{{- end -}}

{{/*
Render the envFrom: entries shared by every Laravel pod.
Mounts the -defaults and -environment secrets, plus -typesense when enabled.
*/}}
{{- define "sail.laravelEnvFrom" -}}
{{- $name := include "sail.name" . -}}
{{- $typesense := dict -}}
{{- if .main -}}
{{- $typesense = .main.typesense | default dict -}}
{{- else -}}
{{- $typesense = .Values.typesense | default dict -}}
{{- end }}
- secretRef:
    name: {{ $name }}-defaults
- secretRef:
    name: {{ $name }}-environment
{{- if $typesense.enabled }}
- secretRef:
    name: {{ $typesense.secret | default (printf "%s-typesense" $name) }}
{{- end }}
{{- end -}}

