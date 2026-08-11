# Release Notes

## [3.8.4](https://github.com/ReyemTech/sail/compare/v3.8.3...v3.8.4) (2026-08-11)


### Bug Fixes

* **build:** allow lan certificate context ([07e839e](https://github.com/ReyemTech/sail/commit/07e839e71ea7363732dd66e7f354b8b85ba453a8))

## [3.8.3](https://github.com/ReyemTech/sail/compare/v3.8.2...v3.8.3) (2026-08-11)


### Bug Fixes

* **runtime:** allow bake without local certificates ([bf384ee](https://github.com/ReyemTech/sail/commit/bf384eeac1feea3ef380ea08e9ec66274636c790))

## [3.8.2](https://github.com/ReyemTech/sail/compare/v3.8.1...v3.8.2) (2026-08-10)


### Bug Fixes

* **network:** enable darwin mdns lifecycle ([99a6443](https://github.com/ReyemTech/sail/commit/99a64438d4972cab3ded96173ee11e523e6fd9e8))

## [3.8.1](https://github.com/ReyemTech/sail/compare/v3.8.0...v3.8.1) (2026-08-10)


### Bug Fixes

* **compose:** migrate legacy runtime builds ([cb30780](https://github.com/ReyemTech/sail/commit/cb307805cea4e7c8040669f1a34e0626c21c8b7b))

## [3.8.0](https://github.com/ReyemTech/sail/compare/v3.7.3...v3.8.0) (2026-08-10)


### Features

* **network:** publish mdns on macos ([2c3e9f1](https://github.com/ReyemTech/sail/commit/2c3e9f1157adf6f6c27ff110422586b742af7f7e))

## [3.7.3](https://github.com/ReyemTech/sail/compare/v3.7.2...v3.7.3) (2026-08-10)


### Bug Fixes

* **network:** harden macos lan setup ([8ed9a5d](https://github.com/ReyemTech/sail/commit/8ed9a5d4a10e489a38d03fc7dee6d57dca589b3f))

## [3.7.2](https://github.com/ReyemTech/sail/compare/v3.7.1...v3.7.2) (2026-08-07)


### Bug Fixes

* **runtime:** install opcache for older php ([0cbe6c0](https://github.com/ReyemTech/sail/commit/0cbe6c07a2a4b421bf846307ef5023bbc8b64db2))
* **runtime:** use bundled alpine opcache ([8ca3577](https://github.com/ReyemTech/sail/commit/8ca35773e8dfdffede81c444db2d06c0a61b1287))

## [3.7.1](https://github.com/ReyemTech/sail/compare/v3.7.0...v3.7.1) (2026-08-07)


### Bug Fixes

* **network:** require tty for mdns prompts ([33c7d21](https://github.com/ReyemTech/sail/commit/33c7d21d9907085517bbb3f22ad8986f54545a84))
* **runtime:** forward php version to bake ([964638d](https://github.com/ReyemTech/sail/commit/964638dd36a6f629a15fa1269d40d79d8e7f51d7))

## [3.7.0](https://github.com/ReyemTech/sail/compare/v3.6.0...v3.7.0) (2026-08-07)


### Features

* **boost:** add LAN networking guidance ([78691a7](https://github.com/ReyemTech/sail/commit/78691a7607b84d41cf0e1ac11c83807c836ff7ba))
* **boost:** add LAN networking guidance ([609b16d](https://github.com/ReyemTech/sail/commit/609b16d9f6bd68e410ca13b9b4021393915d60ae))


### Bug Fixes

* **boost:** clarify Mailpit port allocation ([37d263f](https://github.com/ReyemTech/sail/commit/37d263faec01df4691fb3b5bafb85b8cd44b22b3))
* **boost:** distinguish subnet creation failures ([85225ae](https://github.com/ReyemTech/sail/commit/85225ae1f6631b838f6a4b26b4e559df281fc0d9))
* **runtime:** support lan bake certificates ([c58a4c2](https://github.com/ReyemTech/sail/commit/c58a4c263ef3802337bd721ea8a296e69c873781))

## [3.6.0](https://github.com/ReyemTech/sail/compare/v3.5.2...v3.6.0) (2026-07-25)


### Features

* **build:** source frontend build-time config from sail config ([c76e854](https://github.com/ReyemTech/sail/commit/c76e85413bcaaa87532a9400c06b99c734f82d03))
* **runtime:** forward frontend build-time config to the asset build ([6c03bc6](https://github.com/ReyemTech/sail/commit/6c03bc65e23fe3af365538ae753dd24727f72cc4))
* **runtime:** forward frontend build-time config to the asset build ([887cd57](https://github.com/ReyemTech/sail/commit/887cd57ad891994fd1edd00674d95a6af4b0eecd))

## [3.5.2](https://github.com/ReyemTech/sail/compare/v3.5.1...v3.5.2) (2026-07-24)


### Bug Fixes

* **redis:** resolve AAAA-only Sentinel masters (IPv6 clusters) ([55801fc](https://github.com/ReyemTech/sail/commit/55801fcd896f38b6fa04e13f9ff4ed51ef083a5e))
* **redis:** tolerate transient DNS failures in the Sentinel connector ([0dae594](https://github.com/ReyemTech/sail/commit/0dae594211a23f65ff5e080dbe43b0665c98f7c0))
* **redis:** tolerate transient DNS failures in the Sentinel connector ([47584eb](https://github.com/ReyemTech/sail/commit/47584eb56621a26a4d9872a3ff15db2b9e9d440b))

## [3.5.1](https://github.com/ReyemTech/sail/compare/v3.5.0...v3.5.1) (2026-07-24)


### Bug Fixes

* **network:** raise shared-proxy header buffers to prevent 502 on large headers ([89ee560](https://github.com/ReyemTech/sail/commit/89ee56059695733eaae16ab8a7409b21db519b1f))
* **network:** raise shared-proxy header buffers to prevent 502 on large headers ([d10dc1d](https://github.com/ReyemTech/sail/commit/d10dc1df9eb39d7a5eda8fa0a169533e755c8dc7))

## [3.5.0](https://github.com/ReyemTech/sail/compare/v3.4.3...v3.5.0) (2026-07-24)


### Features

* **network:** --resolver option wires mdns/nip through lan config ([b2b0a06](https://github.com/ReyemTech/sail/commit/b2b0a0629e180ab584a32621550cc42213db963d))
* **network:** add HostIpDetector for LAN IP detection ([3d2ada9](https://github.com/ReyemTech/sail/commit/3d2ada9fac54ab437f40877876415ba8b667dce0))
* **network:** add LanEnvironment nip.io value computation ([93ef9e1](https://github.com/ReyemTech/sail/commit/93ef9e16a46b9bc654e59d004a68735b3a4edc98))
* **network:** add network config section with local-mode defaults ([0211c4a](https://github.com/ReyemTech/sail/commit/0211c4ab4540629e2e02c79dbacaffc5015c12a7))
* **network:** add per-machine HostRegistry for port-slot allocation ([eb5982f](https://github.com/ReyemTech/sail/commit/eb5982f2ba5a0243de56454a534cc63c39698c14))
* **network:** add sail:network command for status and local env seed ([b5f8da9](https://github.com/ReyemTech/sail/commit/b5f8da94bba47a38deb115c95cc1104b1d13bb3c))
* **network:** add sail:proxy command to manage the shared LAN proxy ([071a8a9](https://github.com/ReyemTech/sail/commit/071a8a9dd1f4369e2a2da316b686245fb9630bb9))
* **network:** add SailHome host-state path resolver ([2bc451e](https://github.com/ReyemTech/sail/commit/2bc451e6a3cbdc1953116e5e2659ec102ee4f2f3))
* **network:** configurable network modes — local, LAN (single/multi-project, nip.io/mDNS), lan-direct, plain-HTTP ([3fee103](https://github.com/ReyemTech/sail/commit/3fee10332311207cd27a97544ae37252c09f753f))
* **network:** detect + warn (and offer to fix) missing host avahi for mdns ([bd8347c](https://github.com/ReyemTech/sail/commit/bd8347c76635f659f3b1c55be97387234a56f56d))
* **network:** emit avahi-publish sidecar for mdns overrides ([e5a83df](https://github.com/ReyemTech/sail/commit/e5a83df5b397314414263209cf8ba434df7ced3b))
* **network:** lan mode writes shared-proxy override, SAIL_FILES, port offsets ([1ea6def](https://github.com/ReyemTech/sail/commit/1ea6def67d72bb09a2b3c7a2167e8eb4c9ab6930))
* **network:** lan-direct mode + plain-HTTP TLS toggle (PHP) ([a415c57](https://github.com/ReyemTech/sail/commit/a415c578761e03c1e1b08ec3c8da23afbbdd7863))
* **network:** lan-direct NIC aliasing + plain-HTTP cert-skip (sail-setup) ([601ce7c](https://github.com/ReyemTech/sail/commit/601ce7c5304cc06fb331961a1b2a9156fa3a4517))
* **network:** LanEnvironment supports mdns (.local) resolver ([6b62787](https://github.com/ReyemTech/sail/commit/6b6278775f0690b67c517b168835fb8a51fe7a15))
* **network:** make sail-setup mode-aware (skip host-local steps in lan) ([96d4447](https://github.com/ReyemTech/sail/commit/96d4447de517ddb6a1b06b0e258a8a617ad8a5b9))
* **network:** publish ports via SAIL_BIND_IP (SAIL_IP kept as alias) ([4fbfd67](https://github.com/ReyemTech/sail/commit/4fbfd67c57c22c91df9dcdb9005c77eb78f9a5db))
* **network:** re-run setup when the lan-direct LAN alias is missing (bin/sail) ([7961335](https://github.com/ReyemTech/sail/commit/79613354808d17116f79e65a79ab44e99f3552c2))
* **network:** render shared-proxy compose + per-project lan override ([07f6f89](https://github.com/ReyemTech/sail/commit/07f6f899ef7151b1bc0ac6966bdbef24ac133c36))
* **network:** sail up auto-heals networking; mode-aware setup checks ([71ac7cc](https://github.com/ReyemTech/sail/commit/71ac7cc7e0ab93951843060647790e461db23e98))
* **network:** sail up bootstraps shared network + proxy in lan mode ([58e5697](https://github.com/ReyemTech/sail/commit/58e569744c7de288df77efec23ccb8f2fc7569ee))
* **network:** sail:network --status shows lan domain + assigned ports ([1aaafb3](https://github.com/ReyemTech/sail/commit/1aaafb36bf4a362d62a7bb6612bf2bf37d03e584))
* **network:** sail:network/sail:install --mode=lan (nip.io + LAN bind) ([7a64128](https://github.com/ReyemTech/sail/commit/7a64128fd641d17d5d44e541cb8b8248fadc237b))
* **network:** write lan-mode certs to the shared SAIL_HOME certs dir ([f5c3e16](https://github.com/ReyemTech/sail/commit/f5c3e16c204e1c4f2d3016952ea722480149ef06))


### Bug Fixes

* **add:** pass resolved project name to sail:add compose/env generation ([3ed65aa](https://github.com/ReyemTech/sail/commit/3ed65aad2c748f8a6d333b04ef91534d8afd9dff))
* **build:** correct realpath and empty-ORG/VERSION fallback in bin/sail ([2df5c04](https://github.com/ReyemTech/sail/commit/2df5c040b8a736caad9096e1720a73ccaceb8747))
* **build:** reconcile compose image tag with the baked tag + app-service fallback ([506c86a](https://github.com/ReyemTech/sail/commit/506c86ab51d0cd8fa39ced4fc21dd1de868fb947))
* **build:** show the single invalid environment, not implode() a string ([9f5f0c5](https://github.com/ReyemTech/sail/commit/9f5f0c5a255da0d0c2a2286872861706aa6ef0e4))
* **network:** applyLanConfig reuses stored SAIL_BIND_IP (idempotent re-config) ([6485ef5](https://github.com/ReyemTech/sail/commit/6485ef52d9b67290e2071dafc1abfa0d4d6c523a))
* **network:** atomic HostRegistry::save() (temp file + rename) ([eea42d7](https://github.com/ReyemTech/sail/commit/eea42d79ebb14c2aab70a2031c171bff52f0f449))
* **network:** bin/sail up honors SAIL_FILES override; start proxy after certs ([54fed04](https://github.com/ReyemTech/sail/commit/54fed040ff310cb23e15159c1955161d8adba57c))
* **network:** derive SAIL_FILES base name from composePath (not hardcoded) ([fefb316](https://github.com/ReyemTech/sail/commit/fefb316cf4a05dc7561fef7eaa570e3e2e7a8940))
* **network:** detect a fresh LAN IP on a local-&gt;lan switch ([58abf51](https://github.com/ReyemTech/sail/commit/58abf51c4e5dcb597607d732626429bea7ac72ce))
* **network:** don't write generic certs into the shared lan certs dir ([1d304ce](https://github.com/ReyemTech/sail/commit/1d304ce1562c08f5728e44dc0788d9d72615e135))
* **network:** drop non-portable -R from avahi-publish sidecar ([dc21fbd](https://github.com/ReyemTech/sail/commit/dc21fbdfc42118d1c808cbe6393093d980376f3e))
* **network:** export COMPOSE_PROFILES/SAIL_FILES so Compose honors them ([4fef268](https://github.com/ReyemTech/sail/commit/4fef268e26afaf98cf85d94d17ab25f2a6b5de82))
* **network:** honor an explicit --resolver switch; preserve stored resolver on heal ([4098806](https://github.com/ReyemTech/sail/commit/40988066c8be35801bf1522306f52f310b9e5ab8))
* **network:** honor SAIL_DOCKER_BINARY for shared-network docker calls ([a13951d](https://github.com/ReyemTech/sail/commit/a13951df23337387733e1adc6d0d570cfc1dd582))
* **network:** lan NEEDS_SETUP checks the shared certs dir (stop re-firing) ([5f96581](https://github.com/ReyemTech/sail/commit/5f96581a3f45bdd80c05fa74637bffbad7b57a1b))
* **network:** lock HostRegistry slot allocation; guard atomic save ([cc752a2](https://github.com/ReyemTech/sail/commit/cc752a2ac04a82c2184855d564d9f2d3307ff720))
* **network:** make mDNS .local actually resolve — CNAME sidecar + host-config batteries ([12fe2b7](https://github.com/ReyemTech/sail/commit/12fe2b7a2ea66d6555851020dd6f76fbde72d9d5))
* **network:** make the mDNS avahi sidecar correct + document host prereq ([56d8f7a](https://github.com/ReyemTech/sail/commit/56d8f7af0e00adab51a4e75048b7000abfb8519b))
* **network:** mDNS publisher must survive avahi-daemon restarts ([b156945](https://github.com/ReyemTech/sail/commit/b15694598fd0a72013ce1cf36f3e0b0da351eda3))
* **network:** regenerate a missing lan SAIL_FILES override before bailing ([f253b69](https://github.com/ReyemTech/sail/commit/f253b694e8d8e640c4d1f38976dd80bb99b6b067))
* **network:** regenerate cert on domain change + apply lan config before setup ([a4b4db7](https://github.com/ReyemTech/sail/commit/a4b4db7f7359d0b5580beaa46785ff3645061df5))
* **network:** reuse stored custom domain on lan re-run; check rename in save ([c7c2879](https://github.com/ReyemTech/sail/commit/c7c2879e8900d26b279a9da3135aedc7ab5f9034))
* **network:** sail:proxy status checks real network state ([6dc5888](https://github.com/ReyemTech/sail/commit/6dc588884363d84343535edb8106338b27aca4e6))
* **network:** seed SAIL_BIND_IP from existing SAIL_IP alias on upgrade path ([dc9a3e1](https://github.com/ReyemTech/sail/commit/dc9a3e12a3350785dba752ed355b5b3690038460))

## [3.4.3](https://github.com/ReyemTech/sail/compare/v3.4.2...v3.4.3) (2026-07-16)


### Bug Fixes

* **nginx:** raise FastCGI header buffers past the 4k default ([9d1306d](https://github.com/ReyemTech/sail/commit/9d1306d9daf7e0810ead99d6d2f5ef416eec3dbd))
* **nginx:** raise FastCGI header buffers past the 4k default ([f27fe96](https://github.com/ReyemTech/sail/commit/f27fe9650f525de9b6a652750c3430986eb730d9))

## [3.4.2](https://github.com/ReyemTech/sail/compare/v3.4.1...v3.4.2) (2026-07-10)


### Bug Fixes

* **helm:** expand flow-style parent maps when merging stub defaults ([29a2256](https://github.com/ReyemTech/sail/commit/29a22565394c0a23ce9cb08184fd7bba59d75cdf))
* **helm:** expand flow-style parent maps when merging stub defaults ([d7c87ae](https://github.com/ReyemTech/sail/commit/d7c87ae6dab0532f0f64d06e2f6778d70763b1ea))
* **helm:** preserve consumer values.yaml during chart regeneration ([633ba54](https://github.com/ReyemTech/sail/commit/633ba54da65bb990af2251978a4ca1ad50fbaa99))
* **helm:** preserve consumer values.yaml during chart regeneration ([2733b86](https://github.com/ReyemTech/sail/commit/2733b86c57a29e73700517d844ae083e4051ac65)), closes [#20](https://github.com/ReyemTech/sail/issues/20)
* **helm:** wire REDIS_QUEUE_RETRY_AFTER and modernize cache/session defaults ([36bbd1f](https://github.com/ReyemTech/sail/commit/36bbd1fa7b54b0eb5184f8570b851c39a49f71e6))
* **helm:** wire REDIS_QUEUE_RETRY_AFTER and modernize cache/session defaults ([ec49b35](https://github.com/ReyemTech/sail/commit/ec49b35ecfb320a5547c7f730e4daa7e26a748ab))
* sail up should not restart a running stack and must propagate compose exit code ([5e20d03](https://github.com/ReyemTech/sail/commit/5e20d03e9abf8621ab4fac32501c8b5959f89a68))
* sail up should not restart a running stack and must propagate compose exit code ([bc1f8de](https://github.com/ReyemTech/sail/commit/bc1f8de3a5a33bd786b5fc0e29c19b3e21d0894a))

## [3.4.1](https://github.com/ReyemTech/sail/compare/v3.4.0...v3.4.1) (2026-06-24)


### Miscellaneous Chores

* merge upstream/1.x (laravel/sail v1.57.0..v1.63.0) ([7d630f9](https://github.com/ReyemTech/sail/commit/7d630f9bd3121998bbf719c77a84d85c4c5d55a0))

## [3.4.0](https://github.com/ReyemTech/sail/compare/v3.3.0...v3.4.0) (2026-06-24)


### Features

* **gotenberg:** add Gotenberg HTML→PDF sidecar across compose and helm ([db81e8d](https://github.com/ReyemTech/sail/commit/db81e8d154078994a70b8a98d781d6f5588e05fb))
* **gotenberg:** add Gotenberg HTML→PDF sidecar across compose and Helm ([33e1455](https://github.com/ReyemTech/sail/commit/33e1455877abb8c7862254f6227b9ee24f8227df))


### Bug Fixes

* **helm:** guard nullable gotenberg values before dereferencing ([8cd0351](https://github.com/ReyemTech/sail/commit/8cd0351f25528a07b9d2def52088554f9e0fdc92))
* raise nginx-proxy buffers to avoid 502 on large response headers ([56dc895](https://github.com/ReyemTech/sail/commit/56dc8956ffa5a141a370b8fd83f2590bc1c5a304))
* raise nginx-proxy buffers to avoid 502 on large response headers ([af3ddb4](https://github.com/ReyemTech/sail/commit/af3ddb4bc82a22b5949ba9be00c64bc40cb5a0eb))

## [3.3.0](https://github.com/ReyemTech/sail/compare/v3.2.0...v3.3.0) (2026-05-02)


### Features

* **redis:** retry sentinel reconnect with backoff (3 attempts) ([7b26627](https://github.com/ReyemTech/sail/commit/7b2662797a68695e8789a1d2509d52046c0dfcdd))

## [3.2.0](https://github.com/ReyemTech/sail/compare/v3.1.0...v3.2.0) (2026-05-02)


### Features

* **helm:** extract sail.laravelEnv helpers and wire env on scheduler ([58fbdb1](https://github.com/ReyemTech/sail/commit/58fbdb180abfc3d88a5139c41b84071118440ce5))
* overhaul GitHub Actions CI stub with full production pipeline ([cdab71b](https://github.com/ReyemTech/sail/commit/cdab71b48d109be3eb5f5b337e0f71d1ff8478a1))
* **redis:** add Sentinel-aware phpredis client driver ([7a9688b](https://github.com/ReyemTech/sail/commit/7a9688b6bf411ac858ec52572ae9e1682fabe2d5))


### Bug Fixes

* **helm:** remove rollingUpdate null from Recreate strategy ([e48d984](https://github.com/ReyemTech/sail/commit/e48d984a84dc23549bdc4c5d19918c9221515e8c))

## [3.1.0](https://github.com/ReyemTech/sail/compare/v3.0.4...v3.1.0) (2026-04-19)


### Features

* add values.production.yaml for user-owned helm overrides ([0991678](https://github.com/ReyemTech/sail/commit/0991678e0e5d5b6f688d24e06920c47110b50bd6))


### Bug Fixes

* raise default CPU limit from 1 to 2 cores ([aa40893](https://github.com/ReyemTech/sail/commit/aa40893b93cc1dafdea340ef95abc17b03b196b2))
* skip composer install when vendor/ exists in worker image ([f3fe749](https://github.com/ReyemTech/sail/commit/f3fe74947a1d6ac20e5bfa431a2908b732894df5))

## [3.0.4](https://github.com/ReyemTech/sail/compare/v3.0.3...v3.0.4) (2026-04-18)


### Bug Fixes

* **helm:** clear rollingUpdate when strategy is Recreate ([94fd5cd](https://github.com/ReyemTech/sail/commit/94fd5cd939939d1207cdaf6a1a7490c362b29e0c))

## [3.0.3](https://github.com/ReyemTech/sail/compare/v3.0.2...v3.0.3) (2026-04-17)


### Bug Fixes

* **image:** add listen directive to php-fpm pool config ([5244f12](https://github.com/ReyemTech/sail/commit/5244f12fc352506823606fa94ef504ccd89152fe))
* **image:** add listen directive to php-fpm pool config ([66901d9](https://github.com/ReyemTech/sail/commit/66901d97dfa4653f3eff5e96b4c78849be20a4d5))
* **test:** tolerate optional: true in envFrom order regex ([ef6a3e4](https://github.com/ReyemTech/sail/commit/ef6a3e4c386d17829959003478cf4edb9b212bce))

## [3.0.2](https://github.com/ReyemTech/sail/compare/v3.0.1...v3.0.2) (2026-04-17)


### Bug Fixes

* **helm:** make defaults secret optional in presync-migrate ([5016c1d](https://github.com/ReyemTech/sail/commit/5016c1d4ad83c53593e48fd8d9c4cdba1f6637dc))
* **image:** remove Alpine default www.conf that overrides pool user ([a918cf5](https://github.com/ReyemTech/sail/commit/a918cf5787718c3c993a519abf6f55febbe8025d))

## [3.0.1](https://github.com/ReyemTech/sail/compare/v3.0.0...v3.0.1) (2026-04-17)


### Bug Fixes

* **helm:** Typesense secret sync — remove lookup/randAlphaNum, add ArgoCD ignore ([95277f1](https://github.com/ReyemTech/sail/commit/95277f119bafc1ee8e096678a5899f2f9cea526c))

## [3.0.0](https://github.com/ReyemTech/sail/compare/v2.0.0...v3.0.0) (2026-04-17)


### ⚠ BREAKING CHANGES

* SAIL_BUILD_REMOVE_VENDOR_NODE_MODULES env var renamed to SAIL_BUILD_REMOVE_NODE_MODULES. --remove-vendor-node-modules CLI flag renamed to --remove-node-modules.
* REMOVE_VENDOR_NODE_MODULES renamed to REMOVE_NODE_MODULES

### Features

* add ArgoCD PreSync hook to verify images before deployment ([ce5eebf](https://github.com/ReyemTech/sail/commit/ce5eebff03577efefb552a65962ce6a65d3030aa))
* add Laravel Nightwatch agent sidecar support ([69722a7](https://github.com/ReyemTech/sail/commit/69722a7c3aa5fc50f665a2d934900d9dddae1345))
* add presync migration Job template ([8ac4885](https://github.com/ReyemTech/sail/commit/8ac48854d56b7054ff1300be7cbb642291cba860))
* Allow Laravel Sail to run Pest 4 Browser tests ([#812](https://github.com/ReyemTech/sail/issues/812)) ([019a293](https://github.com/ReyemTech/sail/commit/019a2933ff4a9199f098d4259713f9bc266a874e))
* default vendorPvc disabled, add migrations and imageCheck config ([a120977](https://github.com/ReyemTech/sail/commit/a120977b6a857e811045a424f911c83caa41e589))
* gate image check presync Job on imageCheck.enabled config ([9f34196](https://github.com/ReyemTech/sail/commit/9f341969cde842ff67793546af9551dea33e7965))
* **helm:** add Redis Sentinel env vars to chart ([4a0b1b9](https://github.com/ReyemTech/sail/commit/4a0b1b99b30b897c72885ea713ded848d7dfe37f))
* **helm:** add s3.url support for AWS_URL env var ([d429599](https://github.com/ReyemTech/sail/commit/d429599fb43c963ad6505ac70dd098da53964d9b))
* **helm:** add Typesense subchart and infrastructure secret mapping ([e29dddd](https://github.com/ReyemTech/sail/commit/e29ddddbda2e96d28d0b93d81300aa2a468edb2f))
* keep vendor in production images, only strip node_modules ([da73396](https://github.com/ReyemTech/sail/commit/da733960e6f14ac2c3d324cf7faa0e13d926aeb9))
* **pcov:** change pcov directory ([#670](https://github.com/ReyemTech/sail/issues/670)) ([ab4b2b8](https://github.com/ReyemTech/sail/commit/ab4b2b8292dc7b4b98c3c11084c8fc65f909f4e9))
* remove composer install and migrate from production s6 startup ([004eb75](https://github.com/ReyemTech/sail/commit/004eb752da179a67bbbea8f0161f83f10e3d6c21))
* replace mailhog with mailpit ([#533](https://github.com/ReyemTech/sail/issues/533)) ([4f23063](https://github.com/ReyemTech/sail/commit/4f230634a3163f3442def6a4e6ffdb02b02e14d6))
* resource hygiene — fix s6-log pipeline + chart defaults ([#4](https://github.com/ReyemTech/sail/issues/4)) ([2546599](https://github.com/ReyemTech/sail/commit/254659910b1d159b2af77f9ea9b7b6ddc3372d38))
* simplify scheduler CronJob, remove composer install and PVC mount ([7ad3739](https://github.com/ReyemTech/sail/commit/7ad3739e19f7d68b5969e7a864120dee34f45e04))
* upgrade postgresql-client to 15 ([#564](https://github.com/ReyemTech/sail/issues/564)) ([3042ff8](https://github.com/ReyemTech/sail/commit/3042ff8cf403817c340d5a7762b2d32900239f46))
* Upgrade the Compose file format version to Compose specification ([#601](https://github.com/ReyemTech/sail/issues/601)) ([cf94fd5](https://github.com/ReyemTech/sail/commit/cf94fd5e86ebe11b9ff19dbe91f4e41f603a6cd3))


### Bug Fixes

* Allow postCreateCommand to fail silently in VS Code on Windows ([#626](https://github.com/ReyemTech/sail/issues/626)) ([8145269](https://github.com/ReyemTech/sail/commit/8145269c8b2a72f094e501f73989963f31f887f9))
* auto-rollout Typesense when secret changes ([6431687](https://github.com/ReyemTech/sail/commit/6431687477d0b708e68f072517d477eaf53267f4))
* Change node source repository ([#613](https://github.com/ReyemTech/sail/issues/613)) ([542fff8](https://github.com/ReyemTech/sail/commit/542fff89220c67195ac61bab4eb3cc6572c5cc0c))
* **ci:** remove laravel/sail before linking reyemtech/sail, add PHPStan baseline ([1a119f6](https://github.com/ReyemTech/sail/commit/1a119f62a192f1fc85a6b2d2877f1efacabfd306))
* **ci:** replace upstream Laravel workflows with fork-specific CI ([6521191](https://github.com/ReyemTech/sail/commit/6521191752067b4fb847c0c1c6b1036baccda72d))
* define SAIL_DOCKER_BINARY before first usage in sail script ([bf01ebc](https://github.com/ReyemTech/sail/commit/bf01ebcffe7546809834f8aaf5d346fc3ae382d3))
* fixed swoole extension that gets the SQLSTATE[08006] error ([#715](https://github.com/ReyemTech/sail/issues/715)) ([b4af38e](https://github.com/ReyemTech/sail/commit/b4af38e1ab16fe7aa729eddf5b810229f42d2ad3))
* **helm:** add database.secret env mapping to presync-migrate stub ([a0622ff](https://github.com/ReyemTech/sail/commit/a0622ff92902e2bd918fc9b99f4912475db01f43))
* **helm:** guard nightwatch sidecar against non-map coalescing ([b35e45b](https://github.com/ReyemTech/sail/commit/b35e45bf7f8fa6b9918b408b9cb039dd7629762d))
* **helm:** remove typesense envFrom from presync-migrate stub ([41f9737](https://github.com/ReyemTech/sail/commit/41f973784f1bc0ef1d7065aca57e5cd4afc3388f))
* **helm:** use appname-typesense default in presync and scheduler stubs ([309da20](https://github.com/ReyemTech/sail/commit/309da20613cc60825cac4f4f7150ee330de9d7d9))
* missing \ in dockerfile 8.3 ([#718](https://github.com/ReyemTech/sail/issues/718)) ([0a7e289](https://github.com/ReyemTech/sail/commit/0a7e2891a85eba2d448a9ffc6fc5ce367e924bc1))
* mount docker config for crane registry authentication ([82027c0](https://github.com/ReyemTech/sail/commit/82027c08e8062d4d6a6df0553f6eec2cca7411ca))
* Sail share 504 timeout fix for linux hosts ([#709](https://github.com/ReyemTech/sail/issues/709)) ([f2d43f0](https://github.com/ReyemTech/sail/commit/f2d43f0c01ee00178ce3b08db67cc30d8e4dc142))
* **sail:** Correct YAML syntax in rustfs.stub healthcheck ([#828](https://github.com/ReyemTech/sail/issues/828)) ([1bf3b88](https://github.com/ReyemTech/sail/commit/1bf3b8870b72a258a3b6b5119435835ece522e8a))
* script not loading all app env files ([#482](https://github.com/ReyemTech/sail/issues/482)) ([4fefa0f](https://github.com/ReyemTech/sail/commit/4fefa0f2fb179ba34021ca261b28a393230b2119))
* typesense healthcheck ([#788](https://github.com/ReyemTech/sail/issues/788)) ([e569251](https://github.com/ReyemTech/sail/commit/e5692510f1ef8e0f5096cde2b885d558f8d86592))
* use TCP liveness probe for nightwatch agent sidecar ([c05b26d](https://github.com/ReyemTech/sail/commit/c05b26d832dfc8eaded0141a786a66748f210686))
* Use xdg-open if open does not exist ([#744](https://github.com/ReyemTech/sail/issues/744)) ([ea7ce73](https://github.com/ReyemTech/sail/commit/ea7ce73f569c7fe339a22bf7f3197e4ae1b7cff2))


### Miscellaneous Chores

* **1.x:** release 2.0.0 ([3d71aa1](https://github.com/ReyemTech/sail/commit/3d71aa1c435fc3803f764d709604ead1ae7eab86))
* auto-assign PRs from release-please ([3a1c0e5](https://github.com/ReyemTech/sail/commit/3a1c0e5f33d0dcff346dbf7894ac49084a0cc671))
* Replace mysql/mysql-server:8.0 with mysql:8.4 ([#834](https://github.com/ReyemTech/sail/issues/834)) ([070c7f3](https://github.com/ReyemTech/sail/commit/070c7f34ca8dbece4350fbfe0bab580047dfacc7))
* uncomment xdebug extension ([#837](https://github.com/ReyemTech/sail/issues/837)) ([410b38d](https://github.com/ReyemTech/sail/commit/410b38dd3ec98d5fe4861bc2ed26db3761de7253))
* Update `update-changelog.yml` ([06b4e13](https://github.com/ReyemTech/sail/commit/06b4e13b63329540da2310f191f49b599dc935a5))


### Code Refactoring

* rename remove_vendor_node_modules to remove_node_modules ([c3268f0](https://github.com/ReyemTech/sail/commit/c3268f099fde759f4d3a5256314d58ab0212a184))

## [2.0.0](https://github.com/reyemtech/sail/compare/v1.51.1...v2.0.0) (2026-03-17)


### ⚠ BREAKING CHANGES

* SAIL_BUILD_REMOVE_VENDOR_NODE_MODULES env var renamed to SAIL_BUILD_REMOVE_NODE_MODULES. --remove-vendor-node-modules CLI flag renamed to --remove-node-modules.
* REMOVE_VENDOR_NODE_MODULES renamed to REMOVE_NODE_MODULES

### Features

* add ArgoCD PreSync hook to verify images before deployment ([ceb49a0](https://github.com/reyemtech/sail/commit/ceb49a088c5dcbaae60ae4c3b53026903767dcdb))
* add presync migration Job template ([f8c4f27](https://github.com/reyemtech/sail/commit/f8c4f27c291571e9bf74494ea9e1a4e30dc43386))
* default vendorPvc disabled, add migrations and imageCheck config ([72d5890](https://github.com/reyemtech/sail/commit/72d5890b27e3d8ab0a326da130be5208b6dcdb32))
* gate image check presync Job on imageCheck.enabled config ([bcc8fdb](https://github.com/reyemtech/sail/commit/bcc8fdb0d9e2812ac2bcc4fc04a0b19bd2bb0886))
* keep vendor in production images, only strip node_modules ([a04d86d](https://github.com/reyemtech/sail/commit/a04d86d4cc48a021d94715c6829d6b773040bafb))
* remove composer install and migrate from production s6 startup ([0e29815](https://github.com/reyemtech/sail/commit/0e2981539bdca69c7502a5d033d5e70f95a2d6b1))
* simplify scheduler CronJob, remove composer install and PVC mount ([7868f4e](https://github.com/reyemtech/sail/commit/7868f4efd5878a8f8ad44bcc64e4381c90f28edf))


### Bug Fixes

* **ci:** remove laravel/sail before linking reyemtech/sail, add PHPStan baseline ([9730332](https://github.com/reyemtech/sail/commit/9730332afe735cb4bab2d42b513ce5459639e56b))
* **ci:** replace upstream Laravel workflows with fork-specific CI ([6b58646](https://github.com/reyemtech/sail/commit/6b5864602117bec66780f78bd84ebfef73477431))
* mount docker config for crane registry authentication ([bc506a2](https://github.com/reyemtech/sail/commit/bc506a26970d4533c390118541ed6d9eec781c5d))


### Code Refactoring

* rename remove_vendor_node_modules to remove_node_modules ([47fd17b](https://github.com/reyemtech/sail/commit/47fd17b19e6bcf2912e398dca2376d43bb995696))

## [v1.51.0](https://github.com/laravel/sail/compare/v1.50.0...v1.51.0) - 2025-12-09

* Fix volume path for PostgreSQL data storage by [@JulBeg](https://github.com/JulBeg) in https://github.com/laravel/sail/pull/836
* [1.x] Enable PHP 8.5 XDebug Support by [@sweptsquash](https://github.com/sweptsquash) in https://github.com/laravel/sail/pull/837
* [1.x] Fix AVIF support for GD by [@CasEbb](https://github.com/CasEbb) in https://github.com/laravel/sail/pull/840

## [v1.50.0](https://github.com/laravel/sail/compare/v1.49.1...v1.50.0) - 2025-12-03

* Add PHP 8.5 Support by [@sweptsquash](https://github.com/sweptsquash) in https://github.com/laravel/sail/pull/832

## [v1.49.1](https://github.com/laravel/sail/compare/v1.49.0...v1.49.1) - 2025-12-03

* [1.x] Fix error when `MYSQL_USER` in not set by [@hafezdivandari](https://github.com/hafezdivandari) in https://github.com/laravel/sail/pull/835

## [v1.49.0](https://github.com/laravel/sail/compare/v1.48.1...v1.49.0) - 2025-11-25

* Update Node LTS from 22 to 24 by [@sweptsquash](https://github.com/sweptsquash) in https://github.com/laravel/sail/pull/833
* Replace mysql/mysql-server:8.0 with mysql:8.4 by [@sweptsquash](https://github.com/sweptsquash) in https://github.com/laravel/sail/pull/834

## [v1.48.1](https://github.com/laravel/sail/compare/v1.48.0...v1.48.1) - 2025-11-17

* Remove notice from MySQL service about the MYSQL_EXTRA_OPTIONS env not defined by [@tonysm](https://github.com/tonysm) in https://github.com/laravel/sail/pull/830

## [v1.48.0](https://github.com/laravel/sail/compare/v1.47.0...v1.48.0) - 2025-11-09

* Add rustfs service to Docker Compose and update service list by [@francoism90](https://github.com/francoism90) in https://github.com/laravel/sail/pull/822
* Fix(sail): Correct YAML syntax in rustfs.stub healthcheck by [@jeffersonrucu](https://github.com/jeffersonrucu) in https://github.com/laravel/sail/pull/828

## [v1.47.0](https://github.com/laravel/sail/compare/v1.46.0...v1.47.0) - 2025-10-28

* [1.x] Uncomment CLI workers on install by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/sail/pull/821
* Update PostgreSQL version to 18 by [@abdounikarim](https://github.com/abdounikarim) in https://github.com/laravel/sail/pull/824
* Fix typo in PLAYWRIGHT_BROWSERS_PATH - remove space at the EOL by [@abdounikarim](https://github.com/abdounikarim) in https://github.com/laravel/sail/pull/823
* Update phpstan to version 2 by [@abdounikarim](https://github.com/abdounikarim) in https://github.com/laravel/sail/pull/826
* Update actions/checkout to v5 by [@abdounikarim](https://github.com/abdounikarim) in https://github.com/laravel/sail/pull/825

## [v1.46.0](https://github.com/laravel/sail/compare/v1.45.0...v1.46.0) - 2025-09-23

* mark working directory as safe with git by [@nathanbarrett](https://github.com/nathanbarrett) in https://github.com/laravel/sail/pull/814
* Rename `docker-compose.yml` to `compose.yaml` by [@CasEbb](https://github.com/CasEbb) in https://github.com/laravel/sail/pull/818

## [v1.45.0](https://github.com/laravel/sail/compare/v1.44.0...v1.45.0) - 2025-08-25

* Update PostgreSQL and RabbitMQ stubs to use Alpine variants by [@alexjustesen](https://github.com/alexjustesen) in https://github.com/laravel/sail/pull/810
* feat: Allow Laravel Sail to run Pest 4 Browser tests by [@rogerio-pereira](https://github.com/rogerio-pereira) in https://github.com/laravel/sail/pull/812

## [v1.44.0](https://github.com/laravel/sail/compare/v1.43.1...v1.44.0) - 2025-07-04

* Add tests for laravel 12 and supported vers of php by [@furai](https://github.com/furai) in https://github.com/laravel/sail/pull/801
* Feature: Allow passing in extra options to MYSQL executable by [@ipontt](https://github.com/ipontt) in https://github.com/laravel/sail/pull/805

## [v1.43.1](https://github.com/laravel/sail/compare/v1.43.0...v1.43.1) - 2025-05-19

* Add missing rabbitmq volume by [@kostamilorava](https://github.com/kostamilorava) in https://github.com/laravel/sail/pull/798

## [v1.43.0](https://github.com/laravel/sail/compare/v1.42.0...v1.43.0) - 2025-05-13

* Fix rabbitmq volumes by [@kiani01lab](https://github.com/kiani01lab) in https://github.com/laravel/sail/pull/793
* Add the hostname for RabbitMQ by [@kiani01lab](https://github.com/kiani01lab) in https://github.com/laravel/sail/pull/796
* Add Laravel's official vscode extension to devcontainer stub by [@eamirgh](https://github.com/eamirgh) in https://github.com/laravel/sail/pull/797

## [v1.42.0](https://github.com/laravel/sail/compare/v1.41.1...v1.42.0) - 2025-04-29

* Add the RabbitMQ service by [@kiani01lab](https://github.com/kiani01lab) in https://github.com/laravel/sail/pull/790

## [v1.41.1](https://github.com/laravel/sail/compare/v1.41.0...v1.41.1) - 2025-04-22

* Update logo and socialcard by [@iamdavidhill](https://github.com/iamdavidhill) in https://github.com/laravel/sail/pull/781
* Fix `DB_DATABASE` replacement in `phpunit.xml` by [@choowx](https://github.com/choowx) in https://github.com/laravel/sail/pull/783
* Added configurable user for shell commands by [@fkrzski](https://github.com/fkrzski) in https://github.com/laravel/sail/pull/785
* fix: typesense healthcheck by [@Barbapapazes](https://github.com/Barbapapazes) in https://github.com/laravel/sail/pull/788

## [v1.41.0](https://github.com/laravel/sail/compare/v1.40.0...v1.41.0) - 2025-01-24

* Supports Laravel 12 by [@crynobone](https://github.com/crynobone) in https://github.com/laravel/sail/pull/771
* Add `sail run` command by [@rojtjo](https://github.com/rojtjo) in https://github.com/laravel/sail/pull/770

## [v1.40.0](https://github.com/laravel/sail/compare/v1.39.1...v1.40.0) - 2025-01-13

* enable swoole php 8.4 by [@ariaieboy](https://github.com/ariaieboy) in https://github.com/laravel/sail/pull/766
* Add Valkey support by [@ariaieboy](https://github.com/ariaieboy) in https://github.com/laravel/sail/pull/767
* Update Ondrej PPA key by [@binaryfire](https://github.com/binaryfire) in https://github.com/laravel/sail/pull/768

## [v1.39.1](https://github.com/laravel/sail/compare/v1.39.0...v1.39.1) - 2024-11-27

* [1.x] Remove the default `ubuntu` user by [@rojtjo](https://github.com/rojtjo) in https://github.com/laravel/sail/pull/762

## [v1.39.0](https://github.com/laravel/sail/compare/v1.38.0...v1.39.0) - 2024-11-25

* [1.x] Use Ubuntu 24.04 and Node 22 by [@Jubeki](https://github.com/Jubeki) in https://github.com/laravel/sail/pull/758

## [v1.38.0](https://github.com/laravel/sail/compare/v1.37.1...v1.38.0) - 2024-11-11

* fix: Use xdg-open if open does not exist by [@rqpt](https://github.com/rqpt) in https://github.com/laravel/sail/pull/744
* Add MongoDB extension and service by [@GromNaN](https://github.com/GromNaN) in https://github.com/laravel/sail/pull/748
* fix: Sail share 504 timeout fix for linux hosts by [@rqpt](https://github.com/rqpt) in https://github.com/laravel/sail/pull/709
* Use equals sign (=) instead of space as ENV variable separator by [@jpkleemans](https://github.com/jpkleemans) in https://github.com/laravel/sail/pull/753

## [v1.37.1](https://github.com/laravel/sail/compare/v1.37.0...v1.37.1) - 2024-10-29

* Update typesense.stub to 27.1 by [@Braunson](https://github.com/Braunson) in https://github.com/laravel/sail/pull/741
* Update typesense.stub to correct version tag by [@Braunson](https://github.com/Braunson) in https://github.com/laravel/sail/pull/742

## [v1.37.0](https://github.com/laravel/sail/compare/v1.36.0...v1.37.0) - 2024-10-21

* Add php 8.4 to the list of runtimes by [@jobvink](https://github.com/jobvink) in https://github.com/laravel/sail/pull/740

## [v1.36.0](https://github.com/laravel/sail/compare/v1.35.0...v1.36.0) - 2024-10-10

* [1.x] Update Postgres client to v17 by [@Jubeki](https://github.com/Jubeki) in https://github.com/laravel/sail/pull/737

## [v1.35.0](https://github.com/laravel/sail/compare/v1.34.0...v1.35.0) - 2024-10-08

* Upgrade to Postgres 17 by [@ziadoz](https://github.com/ziadoz) in https://github.com/laravel/sail/pull/735
* Use /data path for minio by [@francoism90](https://github.com/francoism90) in https://github.com/laravel/sail/pull/736

## [v1.34.0](https://github.com/laravel/sail/compare/v1.33.0...v1.34.0) - 2024-09-27

* M3 silicon support and fix 'Hash Sum Mismatch' by [@ConrDev](https://github.com/ConrDev) in https://github.com/laravel/sail/pull/734
* Update logo to support dark/light theme by [@milewski](https://github.com/milewski) in https://github.com/laravel/sail/pull/733

## [v1.33.0](https://github.com/laravel/sail/compare/v1.32.0...v1.33.0) - 2024-09-22

* Pass all command line arguments to wrapped executable by [@JoaquinTrinanes](https://github.com/JoaquinTrinanes) in https://github.com/laravel/sail/pull/728
* Use apt php8.3-swoole again by [@Jubeki](https://github.com/Jubeki) in https://github.com/laravel/sail/pull/731

## [v1.32.0](https://github.com/laravel/sail/compare/v1.31.3...v1.32.0) - 2024-09-11

* [1.x] Add Docker Compose Tests by [@Jubeki](https://github.com/Jubeki) in https://github.com/laravel/sail/pull/721
* Cleanup unneeded code by [@Jubeki](https://github.com/Jubeki) in https://github.com/laravel/sail/pull/724
* Use selenium/standalone-chromium on ARM by [@Jubeki](https://github.com/Jubeki) in https://github.com/laravel/sail/pull/723
* Use selenium/standalone-chromium on AMD and ARM by [@Jubeki](https://github.com/Jubeki) in https://github.com/laravel/sail/pull/722

## [v1.31.3](https://github.com/laravel/sail/compare/v1.31.2...v1.31.3) - 2024-09-03

* fix: missing \ in dockerfile 8.3 by [@saullo](https://github.com/saullo) in https://github.com/laravel/sail/pull/718

## [v1.31.2](https://github.com/laravel/sail/compare/v1.31.1...v1.31.2) - 2024-09-03

* fix: fixed swoole extension that gets the SQLSTATE[08006] error by [@pedrovian4](https://github.com/pedrovian4) in https://github.com/laravel/sail/pull/715

## [v1.31.1](https://github.com/laravel/sail/compare/v1.31.0...v1.31.1) - 2024-08-02

* minio: health check using mc by [@francoism90](https://github.com/francoism90) in https://github.com/laravel/sail/pull/711

## [v1.31.0](https://github.com/laravel/sail/compare/v1.30.2...v1.31.0) - 2024-07-22

* [1.x] Only support MariaDB 11 by [@Jubeki](https://github.com/Jubeki) in https://github.com/laravel/sail/pull/707
* Update EXPOSE port command by [@SamuelMwangiW](https://github.com/SamuelMwangiW) in https://github.com/laravel/sail/pull/706

## [v1.30.2](https://github.com/laravel/sail/compare/v1.30.1...v1.30.2) - 2024-07-05

* [1.x] Use Official MariaDB Healthcheck Script by [@a1383n](https://github.com/a1383n) in https://github.com/laravel/sail/pull/704

## [v1.30.1](https://github.com/laravel/sail/compare/v1.30.0...v1.30.1) - 2024-07-01

* Fixed undefined array key mariadb10|11 error on installation. by [@kursatcanciger](https://github.com/kursatcanciger) in https://github.com/laravel/sail/pull/703

## [v1.30.0](https://github.com/laravel/sail/compare/v1.29.3...v1.30.0) - 2024-06-18

* MariaDB 11 support by [@tomcoonen](https://github.com/tomcoonen) in https://github.com/laravel/sail/pull/698

## [v1.29.3](https://github.com/laravel/sail/compare/v1.29.2...v1.29.3) - 2024-06-12

* Fix meilisearch healthcheck gets to IPv6 instead IPv4 by [@Theprim0](https://github.com/Theprim0) in https://github.com/laravel/sail/pull/697

## [v1.29.2](https://github.com/laravel/sail/compare/v1.29.1...v1.29.2) - 2024-05-16

* [1.x] Install "mariadb-client" package for MariaDB users by [@staudenmeir](https://github.com/staudenmeir) in https://github.com/laravel/sail/pull/693

## [v1.29.1](https://github.com/laravel/sail/compare/v1.29.0...v1.29.1) - 2024-03-20

* [1.x] Make commands lazy by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/sail/pull/683
* Preinstall nano, so default make tinker edit work out of the box by [@negoziator](https://github.com/negoziator) in https://github.com/laravel/sail/pull/685
* Revert opcache for CLI by [@driesvints](https://github.com/driesvints) in https://github.com/laravel/sail/pull/684

## [v1.29.0](https://github.com/laravel/sail/compare/v1.28.2...v1.29.0) - 2024-03-08

* Allow building sail to run PHP as root by [@vmsh0](https://github.com/vmsh0) in https://github.com/laravel/sail/pull/677
* Update MAILER config to use mailpit on L11 by [@SamuelMwangiW](https://github.com/SamuelMwangiW) in https://github.com/laravel/sail/pull/678

## [v1.28.2](https://github.com/laravel/sail/compare/v1.28.1...v1.28.2) - 2024-03-04

* [1.x] Switch from XDEBUG_SESSION to XDEBUG_TRIGGER for sail debug by [@GregMayes](https://github.com/GregMayes) in https://github.com/laravel/sail/pull/675
* Error calling command "sail mariadb" by [@halfbaked](https://github.com/halfbaked) in https://github.com/laravel/sail/pull/674

## [v1.28.1](https://github.com/laravel/sail/compare/v1.28.0...v1.28.1) - 2024-02-23

* [1.x] Use new MariaDB connection if possible by [@staudenmeir](https://github.com/staudenmeir) in https://github.com/laravel/sail/pull/672

## [v1.28.0](https://github.com/laravel/sail/compare/v1.27.4...v1.28.0) - 2024-02-20

* Changing pcov Directory by [@joaopalopes24](https://github.com/joaopalopes24) in https://github.com/laravel/sail/pull/670
* add ffmpeg to support videos, when using Spatie media-library for Videos by [@negoziator](https://github.com/negoziator) in https://github.com/laravel/sail/pull/671

## [v1.27.4](https://github.com/laravel/sail/compare/v1.27.3...v1.27.4) - 2024-02-08

* Fix open in browser with APP_PORT by [@ijpatricio](https://github.com/ijpatricio) in https://github.com/laravel/sail/pull/663

## [v1.27.3](https://github.com/laravel/sail/compare/v1.27.2...v1.27.3) - 2024-01-30

* [1.x] Improves console output by [@nunomaduro](https://github.com/nunomaduro) in https://github.com/laravel/sail/pull/661

## [v1.27.2](https://github.com/laravel/sail/compare/v1.27.1...v1.27.2) - 2024-01-21

* Add Support for Typesense by [@jasonbosco](https://github.com/jasonbosco) in https://github.com/laravel/sail/pull/655
* Lint sail script by [@dimitriacosta](https://github.com/dimitriacosta) in https://github.com/laravel/sail/pull/656
* Make DB_CONNECTION replacement more robust by @taylorotwell in https://github.com/laravel/sail/commit/2276a8d9d6cfdcaad98bf67a34331d100149d5b6

## [v1.27.1](https://github.com/laravel/sail/compare/v1.27.0...v1.27.1) - 2024-01-13

* [1.x] [#651] Don't do anything if no phpunit files are present by [@zack6849](https://github.com/zack6849) in https://github.com/laravel/sail/pull/652

## [v1.27.0](https://github.com/laravel/sail/compare/v1.26.3...v1.27.0) - 2024-01-03

* [1.x] Allow easy customization of the command ran by supervisor's PHP process by [@bram-pkg](https://github.com/bram-pkg) in https://github.com/laravel/sail/pull/645
* [1.x] Default to PHP 8.3 by [@Jubeki](https://github.com/Jubeki) in https://github.com/laravel/sail/pull/647

## [v1.26.3](https://github.com/laravel/sail/compare/v1.26.2...v1.26.3) - 2023-12-02

* [1.x] Add PHP 8.3 xdebug by [@Jubeki](https://github.com/Jubeki) in https://github.com/laravel/sail/pull/642

## [v1.26.2](https://github.com/laravel/sail/compare/v1.26.1...v1.26.2) - 2023-11-27

* Add missing PHP 8.3 extensions by [@hebbet](https://github.com/hebbet) in https://github.com/laravel/sail/pull/640

## [v1.26.1](https://github.com/laravel/sail/compare/v1.26.0...v1.26.1) - 2023-11-20

- Update default user by [@taylorotwell](https://github.com/taylorotwell) in https://github.com/laravel/sail/commit/7a82f5aa364dbee3fd9c52fc464cf0bdd11150ed

## [v1.26.0](https://github.com/laravel/sail/compare/v1.25.0...v1.26.0) - 2023-10-18

- Fix: Allow postCreateCommand to fail silently in VS Code on Windows by [@seanburns326a](https://github.com/seanburns326a) in https://github.com/laravel/sail/pull/626
- Support Laravel 11 and update dependencies by [@Jubeki](https://github.com/Jubeki) in https://github.com/laravel/sail/pull/629
- Use nodejs 20 by [@Jubeki](https://github.com/Jubeki) in https://github.com/laravel/sail/pull/628

## [v1.25.0](https://github.com/laravel/sail/compare/v1.24.1...v1.25.0) - 2023-09-11

- Add Bun by [@punyflash](https://github.com/punyflash) in https://github.com/laravel/sail/pull/616
- Install bun from npm by [@punyflash](https://github.com/punyflash) in https://github.com/laravel/sail/pull/617

## [v1.24.1](https://github.com/laravel/sail/compare/v1.24.0...v1.24.1) - 2023-09-01

- Change node source repository by [@alexpado](https://github.com/alexpado) in https://github.com/laravel/sail/pull/613
- Add PHP 8.3 Runtime (missing extensions excluded) by [@Jubeki](https://github.com/Jubeki) in https://github.com/laravel/sail/pull/614

## [v1.24.0](https://github.com/laravel/sail/compare/v1.23.4...v1.24.0) - 2023-08-27

- Make MEILISEARCH_NO_ANALYTICS environment variable available by [@mawnicat](https://github.com/mawnicat) in https://github.com/laravel/sail/pull/611
- Use Laravel Prompts when available by [@jessarcher](https://github.com/jessarcher) in https://github.com/laravel/sail/pull/612

## [v1.23.4](https://github.com/laravel/sail/compare/v1.23.3...v1.23.4) - 2023-08-17

- Adjust pnpm  to support Sail alias by [@SamuelMTeixeira](https://github.com/SamuelMTeixeira) in https://github.com/laravel/sail/pull/607

## [v1.23.3](https://github.com/laravel/sail/compare/v1.23.2...v1.23.3) - 2023-08-14

- Upgrade the Compose file format version to Compose specification by [@goodjack](https://github.com/goodjack) in https://github.com/laravel/sail/pull/601
- Add PNPM support to enhance dependency management efficiency by [@SamuelMTeixeira](https://github.com/SamuelMTeixeira) in https://github.com/laravel/sail/pull/605

## [v1.23.2](https://github.com/laravel/sail/compare/v1.23.1...v1.23.2) - 2023-08-07

- add fswatch for pest support by [@Thinkro](https://github.com/Thinkro) in https://github.com/laravel/sail/pull/600

## [v1.23.1](https://github.com/laravel/sail/compare/v1.23.0...v1.23.1) - 2023-06-28

- Also publish database init scripts by [@spasstiger23](https://github.com/spasstiger23) in https://github.com/laravel/sail/pull/592

## [v1.23.0](https://github.com/laravel/sail/compare/v1.22.0...v1.23.0) - 2023-06-16

- Add `a` as alias for artisan command by @5thmv in https://github.com/laravel/sail/pull/588

## [v1.22.0](https://github.com/laravel/sail/compare/v1.21.5...v1.22.0) - 2023-05-04

- Remove PHP 7.4 Support by @Jubeki in https://github.com/laravel/sail/pull/580

## [v1.21.5](https://github.com/laravel/sail/compare/v1.21.4...v1.21.5) - 2023-04-24

- Fix opening files from Ignition error page by @NiclasvanEyk in https://github.com/laravel/sail/pull/576
- Add librsvg2-bin package for SVG support by @Bottelet in https://github.com/laravel/sail/pull/575

## [v1.21.4](https://github.com/laravel/sail/compare/v1.21.3...v1.21.4) - 2023-03-30

- Speeds up CLI and tests by enabling OpCache by @lukeraymonddowning in https://github.com/laravel/sail/pull/569

## [v1.21.3](https://github.com/laravel/sail/compare/v1.21.2...v1.21.3) - 2023-03-13

- Enable Expose Global Server Infrastructure by @theutz in https://github.com/laravel/sail/pull/563
- feat: upgrade postgresql-client to 15 by @fedorvladimirov in https://github.com/laravel/sail/pull/564

## [v1.21.2](https://github.com/laravel/sail/compare/v1.21.1...v1.21.2) - 2023-03-06

- Use curl to download composer by @larsnystrom in https://github.com/laravel/sail/pull/561

## [v1.21.1](https://github.com/laravel/sail/compare/v1.21.0...v1.21.1) - 2023-03-01

- Added Imagick to the php runtimes by @ams-ryanolson in https://github.com/laravel/sail/pull/559

## [v1.21.0](https://github.com/laravel/sail/compare/v1.20.2...v1.21.0) - 2023-02-16

- Add `sail open` command. by @xiCO2k in https://github.com/laravel/sail/pull/551
- Update keyring path to new default recommendation by @binaryfire in https://github.com/laravel/sail/pull/552

## [v1.20.2](https://github.com/laravel/sail/compare/v1.20.1...v1.20.2) - 2023-02-08

### Fixed

- Fix `SAIL_SHARE_DOMAIN` default value by @gonzalom in https://github.com/laravel/sail/pull/546

## [v1.20.1](https://github.com/laravel/sail/compare/v1.20.0...v1.20.1) - 2023-02-07

### Fixed

- Fixed the path to devcontainer.stub by @gabrielgry in https://github.com/laravel/sail/pull/544

## [v1.20.0](https://github.com/laravel/sail/compare/v1.19.0...v1.20.0) - 2023-02-05

### Added

- Use symfony/yaml, new Soketi service, and new sail:add command by @tonysm in https://github.com/laravel/sail/pull/532

### Fixed

- Move settings into customizations.vscode by @Kyzegs in https://github.com/laravel/sail/pull/542

## [v1.19.0](https://github.com/laravel/sail/compare/v1.18.1...v1.19.0) - 2023-01-31

### Added

- Add custom domain config to sail share by @mojowill in https://github.com/laravel/sail/pull/531
- Add pest command to sail bin by @MortenDHansen in https://github.com/laravel/sail/pull/534

### Changed

- Replace mailhog with mailpit by @ankurk91 in https://github.com/laravel/sail/pull/533

## [v1.18.1](https://github.com/laravel/sail/compare/v1.18.0...v1.18.1) - 2023-01-12

### Changed

- Update devcontainer stub (vscode customizations) by @mojgit in https://github.com/laravel/sail/pull/528

## [v1.18.0](https://github.com/laravel/sail/compare/v1.17.0...v1.18.0) - 2023-01-10

### Added

- Laravel v10 Support by @driesvints in https://github.com/laravel/sail/pull/527

## [v1.17.0](https://github.com/laravel/sail/compare/v1.16.6...v1.17.0) - 2022-12-22

### Changed

- Upgrade to Postgres 15 by @Jubeki in https://github.com/laravel/sail/pull/519
- Install `dnsutils` package to use `dig` command by @buismaarten in https://github.com/laravel/sail/pull/520

## [v1.16.6](https://github.com/laravel/sail/compare/v1.16.5...v1.16.6) - 2022-12-19

### Changed

- Add PHP 8.2 pcov extension again by @Jubeki in https://github.com/laravel/sail/pull/515

## [v1.16.5](https://github.com/laravel/sail/compare/v1.16.4...v1.16.5) - 2022-12-14

### Changed

- Add Forward Memcached Port by @dammy001 in https://github.com/laravel/sail/pull/512

## [v1.16.4](https://github.com/laravel/sail/compare/v1.16.3...v1.16.4) - 2022-12-12

### Fixed

- Changing ubuntu keyserver to use curl by @jseitel in https://github.com/laravel/sail/pull/508

## [v1.16.3](https://github.com/laravel/sail/compare/v1.16.2...v1.16.3) - 2022-11-21

### Fixed

- Fix usage of none for services list by @jf-prevost in https://github.com/laravel/sail/pull/495

## [v1.16.2](https://github.com/laravel/sail/compare/v1.16.1...v1.16.2) - 2022-09-28

### Fixed

- Add extra hosts to Selenium by @nomnoms12 in https://github.com/laravel/sail/pull/485

## [v1.16.1](https://github.com/laravel/sail/compare/v1.16.0...v1.16.1) - 2022-09-26

### Fixed

- Script not loading all app env files by @LouisHaftmann in https://github.com/laravel/sail/pull/482

## [v1.16.0](https://github.com/laravel/sail/compare/v1.15.4...v1.16.0) - 2022-08-31

### Added

- PHP 8.2 Support by @Jubeki in https://github.com/laravel/sail/pull/473

## [v1.15.4](https://github.com/laravel/sail/compare/v1.15.3...v1.15.4) - 2022-08-17

### Fixed

- Don't error when docker is not available by @jessarcher in https://github.com/laravel/sail/pull/468

## [v1.15.3](https://github.com/laravel/sail/compare/v1.15.2...v1.15.3) - 2022-08-17

### Changed

- Build and pull images on install by @jessarcher in https://github.com/laravel/sail/pull/467

## [v1.15.2](https://github.com/laravel/sail/compare/v1.15.1...v1.15.2) - 2022-08-08

### Fixed

- Fix splitting SAIL_FILES into array by @mortenscheel in https://github.com/laravel/sail/pull/458

## [v1.15.1](https://github.com/laravel/sail/compare/v1.15.0...v1.15.1) - 2022-07-21

### Fixed

- Fix ubuntu versions for PHP 7.4 & 8.0 runtimes by @taylorotwell in https://github.com/laravel/sail/commit/2fe64c0b45a3af56cac0af638c8020a8adc860d7

## [v1.15.0](https://github.com/laravel/sail/compare/v1.14.11...v1.15.0) - 2022-06-24

### Added

- Adds `sail pint` by @nunomaduro in https://github.com/laravel/sail/pull/439

### Changed

- Publish the Vite port by @jessarcher in https://github.com/laravel/sail/pull/433

### Fixed

- Fixed devcontainer permissions by @GoodM4ven in https://github.com/laravel/sail/pull/438
- Update default PostgreSQL versions for PHP 8.0 and 7.4 runtimes by @driesvints in https://github.com/laravel/sail/pull/441

## [v1.14.11](https://github.com/laravel/sail/compare/v1.14.10...v1.14.11) - 2022-06-14

### Fixed

- Revert "Expose 8080 port for hot module replacement" by @jessarcher in https://github.com/laravel/sail/pull/432

## [v1.14.10](https://github.com/laravel/sail/compare/v1.14.9...v1.14.10) - 2022-06-09

### Fixed

- Fix testing DB creation by @jessarcher in https://github.com/laravel/sail/pull/429

## [v1.14.9](https://github.com/laravel/sail/compare/v1.14.8...v1.14.9) - 2022-06-06

### Changed

- Allow for creation of databases needed for parallel testing by @bram-pkg in https://github.com/laravel/sail/pull/424

## [v1.14.8](https://github.com/laravel/sail/compare/v1.14.7...v1.14.8) - 2022-05-31

### Changed

- Run supervisord with pid 1 by @ryoluo in https://github.com/laravel/sail/pull/419

## [v1.14.7](https://github.com/laravel/sail/compare/v1.14.6...v1.14.7) - 2022-05-21

### Changed

- Update meilisearch stub to reflect new data path by @tdondich in https://github.com/laravel/sail/pull/414

## [v1.14.6](https://github.com/laravel/sail/compare/v1.14.5...v1.14.6) - 2022-05-18

### Fixed

- Checks if docker compose or docker-compose is installed by @affektde in https://github.com/laravel/sail/pull/409

## [v1.14.5](https://github.com/laravel/sail/compare/v1.14.4...v1.14.5) - 2022-05-16

### Changed

- Updated sail helps section by @mehdirajabi59 in https://github.com/laravel/sail/pull/407
- Cleans up deprecated apt-key usage by @tbollinger in https://github.com/laravel/sail/pull/408
- use docker compose (GO) by @erfantkerfan in https://github.com/laravel/sail/pull/405

## [v1.14.4](https://github.com/laravel/sail/compare/v1.14.3...v1.14.4) - 2022-05-12

### Fixed

- Fixes incorrectly referenced distro https://github.com/laravel/sail/commit/0e0e51f19c758c79acbda11e3870641fbad5b7d9

## [v1.14.3](https://github.com/laravel/sail/compare/v1.14.2...v1.14.3) - 2022-05-10

### Changed

- Changed Ubuntu 21.10 to Ubuntu 22.04 LTS by @mehdirajabi59 in https://github.com/laravel/sail/pull/395

## [v1.14.2](https://github.com/laravel/sail/compare/v1.14.1...v1.14.2) - 2022-05-10

### Fixed

- Allow Sail to read from phpunit.xml and phpunit.xml.dist when running the install command by @kylemilloy in https://github.com/laravel/sail/pull/394
- Fix missing usage of POSTGRES_VERSION by @driesvints in https://github.com/laravel/sail/pull/398

## [v1.14.1](https://github.com/laravel/sail/compare/v1.14.0...v1.14.1) - 2022-05-02

### Changed

- Expose 8080 port for hot module replacement by @ryoluo in https://github.com/laravel/sail/pull/391

## [v1.14.0](https://github.com/laravel/sail/compare/v1.13.10...v1.14.0) - 2022-04-27

### Added

- Create a dedicated testing database by @jessarcher in https://github.com/laravel/sail/pull/388

### Fixed

- Fix apt-key for WSL by @Evertt in https://github.com/laravel/sail/pull/389

## [v1.13.10](https://github.com/laravel/sail/compare/v1.13.9...v1.13.10) - 2022-04-14

### Fixed

- Fix apt-key for WSL by @driesvints in https://github.com/laravel/sail/pull/384

## [v1.13.9](https://github.com/laravel/sail/compare/v1.13.8...v1.13.9) - 2022-04-04

### Changed

- Update default PostgreSQL version to v14 by @ariaieboy in https://github.com/laravel/sail/pull/373

## [v1.13.8](https://github.com/laravel/sail/compare/v1.13.7...v1.13.8) - 2022-03-23

### Changed

- Update ondrej/php Repository Details by @amayer5125 in https://github.com/laravel/sail/pull/360
- Shell - display available commands / help section by @WalterWoshid in https://github.com/laravel/sail/pull/359

### Fixes

- Fixes docker-compose not found in non-bash shells by @ribeirobreno in https://github.com/laravel/sail/pull/364

## [v1.13.7](https://github.com/laravel/sail/compare/v1.13.6...v1.13.7) - 2022-03-15

### Fixed

- The input device is not a TTY by @ribeirobreno in https://github.com/laravel/sail/pull/353
- `SAIL_FILE` environment variable prevents using docker-compose.override.yml by @ribeirobreno in https://github.com/laravel/sail/pull/355

## [v1.13.6](https://github.com/laravel/sail/compare/v1.13.5...v1.13.6) - 2022-03-08

### Changed

- Allow overriding docker-compose.yml path using ENV by @prageeth in https://github.com/laravel/sail/pull/352 & @taylorotwell in https://github.com/laravel/sail/commit/6205041336b09b965af1d6af29261584e787bf52

## [v1.13.5](https://github.com/laravel/sail/compare/v1.13.3...v1.13.5) - 2022-02-22

### Changed

- Revert "Install regular PHP packages instead of dev versions" by @taylorotwell in https://github.com/laravel/sail/pull/342

## [v1.13.4](https://github.com/laravel/sail/compare/v1.13.3...v1.13.4) - 2022-02-17

### Changed

- Install regular PHP packages instead of dev versions by @bramdevries in https://github.com/laravel/sail/pull/340
- Update Ubuntu by @taylorotwell in https://github.com/laravel/sail/commit/57d2942d5edd89b2018d0a3447da321fa35baac7

## [v1.13.3](https://github.com/laravel/sail/compare/v1.13.2...v1.13.3) - 2022-02-15

### Changed

- Support Newer Docker Compose Exit Statuses by @amayer5125 in https://github.com/laravel/sail/pull/331

### Fixed

- Typo in replace when checking for ARM for Seleium by @aprat84 in https://github.com/laravel/sail/pull/330

## [v1.13.2](https://github.com/laravel/sail/compare/v1.13.1...v1.13.2) - 2022-02-08

### Fixed

- Fix a typo in the "phpunit" command ([#329](https://github.com/laravel/sail/pull/329))

## [v1.13.1 (2022-01-20)](https://github.com/laravel/sail/compare/v1.13.0...v1.13.1)

### Changed

- Update for Meilisearch ARM support ([#315](https://github.com/laravel/sail/pull/315))

### Fixed

- Fix php8.0-dev depending on php8.1-cli ([#316](https://github.com/laravel/sail/pull/316))

## [v1.13.0 (2022-01-18)](https://github.com/laravel/sail/compare/v1.12.12...v1.13.0)

### Added

- Add phpunit alias to sail binary ([#310](https://github.com/laravel/sail/pull/310))

### Changed

- Add separator between volume names ([#312](https://github.com/laravel/sail/pull/312))

## [v1.12.12 (2021-12-16)](https://github.com/laravel/sail/compare/v1.12.11...v1.12.12)

### Fixed

- Revert "Set meilisearch data path" ([#301](https://github.com/laravel/sail/pull/301))

## [v1.12.11 (2021-12-14)](https://github.com/laravel/sail/compare/v1.12.10...v1.12.11)

### Added

- Set meilisearch data path ([#299](https://github.com/laravel/sail/pull/299))

## [v1.12.10 (2021-12-07)](https://github.com/laravel/sail/compare/v1.12.9...v1.12.10)

### Fixed

- ARM based container on Apple Silicon for Selenium ([#294](https://github.com/laravel/sail/pull/294))

## [v1.12.9 (2021-11-30)](https://github.com/laravel/sail/compare/v1.12.8...v1.12.9)

### Changed

- Make PHP 8.1 the default runtime ([#292](https://github.com/laravel/sail/pull/292))

## [v1.12.8 (2021-11-26)](https://github.com/laravel/sail/compare/v1.12.7...v1.12.8)

## Changed

- Revert "Switch to PHP 8.1" ([#291](https://github.com/laravel/sail/pull/291))

## [v1.12.7 (2021-11-26)](https://github.com/laravel/sail/compare/v1.12.6...v1.12.7)

### Changed

- Make PHP 8.1 the default runtime ([#289](https://github.com/laravel/sail/pull/289))

## [v1.12.6 (2021-11-23)](https://github.com/laravel/sail/compare/v1.12.5...v1.12.6)

### Changed

- Add npm update to Dockerfile ([#285](https://github.com/laravel/sail/pull/285))

## [v1.12.5 (2021-11-16)](https://github.com/laravel/sail/compare/v1.12.4...v1.12.5)

### Changed

- Re-enable previously disabled PHP 8.1 extensions ([#278](https://github.com/laravel/sail/pull/278))
- Add platform setting to Meilisearch config ([1286886](https://github.com/laravel/sail/commit/1286886ec04f9101b756221c90ec766741459db4))

## [v1.12.4 (2021-11-09)](https://github.com/laravel/sail/compare/v1.12.3...v1.12.4)

### Fixed

- Fix `NODE_VERSION` on build ([#274](https://github.com/laravel/sail/pull/274))

## [v1.12.3 (2021-11-05)](https://github.com/laravel/sail/compare/v1.12.2...v1.12.3)

### Changed

- Update MySQL stub for Apple Silicon ([#272](https://github.com/laravel/sail/pull/272))

## [v1.12.2 (2021-10-26)](https://github.com/laravel/sail/compare/v1.12.1...v1.12.2)

### Fixed

- Revert "Adds a check and error for APP_SERVICE being accurate." ([#264](https://github.com/laravel/sail/pull/264))

## [v1.12.1 (2021-10-26)](https://github.com/laravel/sail/compare/v1.12.0...v1.12.1)

### Changed

- Adds a check and error for `APP_SERVICE` being accurate ([#258](https://github.com/laravel/sail/pull/258))
- Allow `NODE_VERSION` variable ([#261](https://github.com/laravel/sail/pull/261))

## [v1.12.0 (2021-10-12)](https://github.com/laravel/sail/compare/v1.11.0...v1.12.0)

### Added

- PHP 8.1 support ([#254](https://github.com/laravel/sail/pull/254))

## [v1.11.0 (2021-10-01)](https://github.com/laravel/sail/compare/v1.10.1...v1.11.0)

### Added

- Added support for "docker compose" command syntax

## [v1.10.2 (2021-09-28)](https://github.com/laravel/sail/compare/v1.10.1...v1.10.2)

### Changed

- Environment variable for share subdomain ([#239](https://github.com/laravel/sail/pull/239))

## [v1.10.1 (2021-08-24)](https://github.com/laravel/sail/compare/v1.10.0...v1.10.1)

### Changed

- Adding extra_hosts to the compose file stubs ([#222](https://github.com/laravel/sail/pull/222))
- Allow skip of sail checks ([#224](https://github.com/laravel/sail/pull/224))

## [v1.10.0 (2021-08-17)](https://github.com/laravel/sail/compare/v1.9.0...v1.10.0)

### Added

- Add devcontainer to install command ([#218](https://github.com/laravel/sail/pull/218))

### Changed

- Removes hardcoded service name from `APP_URL` in `dusk` and `dusk:fails` command ([#219](https://github.com/laravel/sail/pull/219))

## [v1.9.0 (2021-08-03)](https://github.com/laravel/sail/compare/v1.8.6...v1.9.0)

### Added

- Xdebug 3.0 support ([#209](https://github.com/laravel/sail/pull/209))

### Changed

- Make sail script publishable ([#201](https://github.com/laravel/sail/pull/201), [#202](https://github.com/laravel/sail/pull/202))
- Pass additional arguments to shell / root-shell commands ([#208](https://github.com/laravel/sail/pull/208))

### Fixed

- Call source `.env` before exporting bash environment variables ([#207](https://github.com/laravel/sail/pull/207))

## [v1.8.6 (2021-07-15)](https://github.com/laravel/sail/compare/v1.8.5...v1.8.6)

### Fixed

- Fixes missing backslash ([#196](https://github.com/laravel/sail/pull/196))

## [v1.8.5 (2021-07-13)](https://github.com/laravel/sail/compare/v1.8.4...v1.8.5)

### Changed

- Minio Console Port ([#188](https://github.com/laravel/sail/pull/188))

## [v1.8.4 (2021-07-06)](https://github.com/laravel/sail/compare/v1.8.3...v1.8.4)

### Changed

- Update to Ubuntu 21.04 ([#177](https://github.com/laravel/sail/pull/177))
- Add pcov to php 8.0 runtime ([#183](https://github.com/laravel/sail/pull/183))

### Fixed

- Append random subdomain by default ([#175](https://github.com/laravel/sail/pull/175))

### Removed

- Remove Unused SEDCMD ([#179](https://github.com/laravel/sail/pull/179))

## [v1.8.3 (2021-06-29)](https://github.com/laravel/sail/compare/v1.8.2...v1.8.3)

### Fixed

- Revert Ubuntu 21.04 changes ([#174](https://github.com/laravel/sail/pull/174))

## [v1.8.2 (2021-06-29)](https://github.com/laravel/sail/compare/v1.8.1...v1.8.2)

### Changed

- Share/Expose options and cleanup on exit ([#168](https://github.com/laravel/sail/pull/168), [44c7087](https://github.com/laravel/sail/commit/44c7087026a0637471e544237d608a2e1173dc77))
- Update to Ubuntu 21.04 ([#169](https://github.com/laravel/sail/pull/169), [0df641d](https://github.com/laravel/sail/commit/0df641dd2d7f2f42d24aef638e2e579f6ac7e57c), [484b928](https://github.com/laravel/sail/commit/484b9284d46bfe3e1e6a2ed71477bb4b70166070))

## [v1.8.1 (2021-06-08)](https://github.com/laravel/sail/compare/v1.8.0...v1.8.1)

### Fixed

- Fix if statement in `sail` binary ([414fd19](https://github.com/laravel/sail/commit/414fd19858379fd3c0277194904ffb95617d7ee6)

## [v1.8.0 (2021-06-08)](https://github.com/laravel/sail/compare/v1.7.0...v1.8.0)

### Added

- Add proxy to vendor binaries ([#154](https://github.com/laravel/sail/pull/154))

### Changed

- Use node.js v16.x ([#155](https://github.com/laravel/sail/pull/155))
- Update Sail script to only exit if Main Exits ([#156](https://github.com/laravel/sail/pull/156))

### Fixed

- Append MeiliSearch and MinIO to depends ([#151](https://github.com/laravel/sail/pull/151))
- Append MeiliSearch HealthCheck ([#150](https://github.com/laravel/sail/pull/150))

## [v1.7.0 (2021-05-25)](https://github.com/laravel/sail/compare/v1.6.0...v1.7.0)

### Added

- Add Redis CLI command ([#140](https://github.com/laravel/sail/pull/140))

### Fixed

- Add retries & timeout to healthcheck ([#143](https://github.com/laravel/sail/pull/143))

## [v1.6.0 (2021-05-18)](https://github.com/laravel/sail/compare/v1.5.1...v1.6.0)

### Added

- Add MinIO to sail:install Command ([#128](https://github.com/laravel/sail/pull/128))

### Changed

- Clear pecl caches & tmp files during Swoole extension install ([#134](https://github.com/laravel/sail/pull/134))

### Fixed

- Fix mariaDB Health check ([#126](https://github.com/laravel/sail/pull/126))

## [v1.5.1 (2021-05-11)](https://github.com/laravel/sail/compare/v1.5.0...v1.5.1)

### Changed

- Use MySQL shell when running mariadb ([#119](https://github.com/laravel/sail/pull/119))

### Fixed

- Fix mysql health check ([#125](https://github.com/laravel/sail/pull/125))

## [v1.5.0 (2021-04-20)](https://github.com/laravel/sail/compare/v1.4.12...v1.5.0)

### Added

- MariaDB support ([#111](https://github.com/laravel/sail/pull/111))

## [v1.4.12 (2021-04-13)](https://github.com/laravel/sail/compare/v1.4.11...v1.4.12)

### Fixed

- Load missing PECL package index before installing Swoole ([#94](https://github.com/laravel/sail/pull/94))

## [v1.4.11 (2021-04-06)](https://github.com/laravel/sail/compare/v1.4.10...v1.4.11)

### Changed

- Add Swoole ([9cf7a28](https://github.com/laravel/sail/commit/9cf7a289fbae184f8468188c582ea5a604ac1012), [0706de0](https://github.com/laravel/sail/commit/0706de0c6a80e6f04861ffb875f9e13c63568ccb))

## [v1.4.10 (2021-03-30)](https://github.com/laravel/sail/compare/v1.4.9...v1.4.10)

### Changed

- Database default user name and password ([#84](https://github.com/laravel/sail/pull/84))

### Fixed

- Patch issue with environment database password replacement ([#87](https://github.com/laravel/sail/pull/87))

## [v1.4.9 (2021-03-23)](https://github.com/laravel/sail/compare/v1.4.8...v1.4.9)

### Fixed

- Use different DB user & password for Sail ([#75](https://github.com/laravel/sail/pull/75))

## [v1.4.8 (2021-03-16)](https://github.com/laravel/sail/compare/v1.4.7...v1.4.8)

### Fixed

- Update the publish command to consider PHP 7.4 ([#68](https://github.com/laravel/sail/pull/68))

## [v1.4.7 (2021-03-09)](https://github.com/laravel/sail/compare/v1.4.6...v1.4.7)

### Fixed

- Add missing PostgreSQL clients ([#64(https://github.com/laravel/sail/pull/64))
- Use latest expose container ([cebaebc](https://github.com/laravel/sail/commit/cebaebc0bb3806f4cf7bc71564acbfe8c12a8923))

## [v1.4.6 (2021-03-03)](https://github.com/laravel/sail/compare/v1.4.5...v1.4.6)

### Fixed

- Update share command ([59ee7e2](https://github.com/laravel/sail/commit/59ee7e2b2efeb644eabea719186db91d11666733))

## [v1.4.5 (2021-03-03)](https://github.com/laravel/sail/compare/v1.4.4...v1.4.5)

### Fixes

- Replace `DB_PORT` and `DB_CONNECTION` for pgsql ([#63](https://github.com/laravel/sail/pull/63))
- Update share command ([0348ec8](https://github.com/laravel/sail/commit/0348ec8c13fedc4bafc917b9d65721cd475390bf))

## [v1.4.4 (2021-03-02)](https://github.com/laravel/sail/compare/v1.4.3...v1.4.4)

### Changed

- Re-add memcached ([#62](https://github.com/laravel/sail/pull/62))

### Fixed

- Fix pgsql.stub volumes typo ([#60](https://github.com/laravel/sail/pull/60))

## [v1.4.3 (2021-02-22)](https://github.com/laravel/sail/compare/v1.4.2...v1.4.3)

### Changed

- Update flag name ([0200ce6](https://github.com/laravel/sail/commit/0200ce6e0f697699bce036c42d91f1daab8039a8))

## [v1.4.2 (2021-02-22)](https://github.com/laravel/sail/compare/v1.4.1...v1.4.2)

### Changed

- Removed comments ([a317a1a](https://github.com/laravel/sail/commit/a317a1af337ffc07c63ea5a4e04784fdb58ea9df))

## [v1.4.1 (2021-02-23)](https://github.com/laravel/sail/compare/v1.4.0...v1.4.1)

### Changed

- Back out feature ([87c63c2](https://github.com/laravel/sail/commit/87c63c2956749f66e43467d4a730b917ef7428b7))

## [v1.4.0 (2021-02-23)](https://github.com/laravel/sail/compare/v1.3.1...v1.4.0)

### Added

- Implement interactive choice and Meilisearch ([#58](https://github.com/laravel/sail/pull/58), [b78093b](https://github.com/laravel/sail/commit/b78093b02c328d82e27cdacfb20568c49cd980c4))

### Changed

- Display message after installing Sail ([#56](https://github.com/laravel/sail/pull/56))

### Fixed

- Change supervisord logfile and pidfile settings ([#57](https://github.com/laravel/sail/pull/57))

### Removed

- Remove memcached stub ([3a4fac1](https://github.com/laravel/sail/commit/3a4fac159b92424d2ff3472ce182be14fc1cb080))

## [v1.3.1 (2021-02-09)](https://github.com/laravel/sail/compare/v1.3.0...v1.3.1)

### Changed

- Inform user when running docker-compose down ([#52](https://github.com/laravel/sail/pull/52))
- Cleanup supervisord warnings on start ([#53](https://github.com/laravel/sail/pull/53))

## [v1.3.0 (2021-01-26)](https://github.com/laravel/sail/compare/v1.2.0...v1.3.0)

### Added

- Add support for `dusk:fails` ([#43](https://github.com/laravel/sail/pull/43))

### Fixed

- Append PostgreSQL HealthCheck ([#41](https://github.com/laravel/sail/pull/41))
- Use non-root MySQL password for `sail mysql` ([#45](https://github.com/laravel/sail/pull/45))

## [v1.2.0 (2021-01-19)](https://github.com/laravel/sail/compare/v1.1.0...v1.2.0)

### Added

- PostgreSQL Support ([#28](https://github.com/laravel/sail/pull/28))

### Changed

- Add healthcheck for mysql and redis service in docker-compose ([#36](https://github.com/laravel/sail/pull/36))
- Update Mailhog env variables ([bf10c80](https://github.com/laravel/sail/commit/bf10c804057f8d0be615c71acbc46c7328cd652c))

## [v1.1.0 (2021-01-05)](https://github.com/laravel/sail/compare/v1.0.1...v1.1.0)

### Added

- Yarn Support ([#29](https://github.com/laravel/sail/pull/29))
- root-shell added to bin/sail ([#33](https://github.com/laravel/sail/pull/33))

### Changed

- Add sail bash to Initiate a Bash shell within the application container ([#30](https://github.com/laravel/sail/pull/30))

### Fixed

- Send error messages to STDERR ([#32](https://github.com/laravel/sail/pull/32))

## [v1.0.1 (2020-12-22)](https://github.com/laravel/sail/compare/v1.0.0...v1.0.1)

### Fixed

- Fix a bug with memcached ([7457004](https://github.com/laravel/sail/commit/7457004969dd62fa727fbc596bb2accccb1409a5))

## v1.0.0 (2020-12-22)

Initial stable release.
