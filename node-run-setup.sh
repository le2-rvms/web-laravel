#!/bin/sh
set -eux

rm -f public/hot
rm -rf public/build

echo "Production/Staging: dry run setup."

# 把源切到 npmmirror 后，npm audit 会去调用安全审计接口（/-/npm/v1/security/*），而这个镜像没实现这些接口
npm config set registry https://registry.npmmirror.com
npm set audit=false
# npm install --verbose --no-audit --registry=https://registry.npmmirror.com
npm install -g pnpm

pnpm install
pnpm ls
pnpm run build --mode ${APP_ENV}
