# interface-api-service

基于 [Swoolefy](https://github.com/bingcool/swoolefy) 的微服务**统一接口契约**仓库。多个业务服务的 **Interface、DTO、Request、Response、Const、Enum** 等定义集中在同一接口层，作为独立的 Composer 包发布；各服务通过依赖安装即可共享契约，避免接口定义分散、版本不一致的问题。

契约设计与运行时约定见 Swoolefy 文档：[InterfaceApi 规范](https://github.com/bingcool/swoolefy/blob/master/docs/InterfaceApi.md)。

## 仓库职责

| 内容 | 说明 |
|------|------|
| **业务契约** | 按服务划分目录，例如 `{ServiceName}/App/Module/...` 下的 `*ApiInterface`、Request/Response、DTO、枚举与常量 |
| **Support** | 公共基础设施（路由/文档注解、Request/Response 基类、校验、Client 基类等），命名空间 `InterfaceApi\Support\`，详见 [Support/README.md](Support/README.md) |
| **bin/** | 引用边界检查、HTTP Client 与 OpenAPI 文档生成脚本 |

生成物（各模块 `Client/*.php`、OpenAPI YAML）由工具链生成，**不要**手改生成文件；应修改契约接口后重新执行生成命令。

## 推荐目录布局

将本仓库克隆到与业务项目**同一父目录**下，业务服务通过 Composer 引用本包即可：

```text
wwwphp/                          # 工作区根目录（示例）
├── interface-api-service/       # 本仓库（Composer 包 bingcool/interface-api）
├── schedule-job/                # 业务服务 A
├── order-biz-service/           # 业务服务 B
└── ...
```

契约生成脚本（`bin/generate-*.php`）**仅在本仓库根目录**运行，不依赖业务仓路径。

## 环境要求

- PHP **>= 8.4**
- 消费方业务服务需依赖 **bingcool/swoolefy**，并通过 `App\Autoloader` 加载 `InterfaceApi\` 命名空间（与 Swoolefy InterfaceApi 文档一致）

## 在业务服务中引用

### 方式一：Composer Path 仓库（本地同级目录，推荐）

在业务服务根目录的 `composer.json` 中增加 path 仓库并声明依赖：

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "../interface-api-service",
      "options": {
        "symlink": true
      }
    }
  ],
  "require": {
    "bingcool/interface-api": "@dev"
  }
}
```

然后在业务服务目录执行：

```bash
composer update bingcool/interface-api
```

安装后，PSR-4 命名空间 `InterfaceApi\` 由包根目录自动映射（见本仓库 `composer.json` 的 `autoload`）。

### 方式二：VCS / Packagist 发布

将 `bingcool/interface-api` 发布到私有 Packagist 或 Git 仓库后，业务服务按常规定义 `require` 即可，无需 path 仓库。

### 服务间调用

- **服务端**：在 Swoolefy 应用中实现契约里的 `*ApiInterface`，HttpRoute 与注解与契约保持一致。
- **调用方**：使用生成出的 HTTP Client（继承 `InterfaceApi\Support\BaseClientApi`），或通过 OpenAPI 对接；Client 由本仓库工具生成，见下文命令。

## 契约目录约定（示例）

业务契约**不要**放在 `Support/` 下，应按服务名分目录，例如：

```text
interface-api-service/
├── Support/                 # 公共基础层（慎改）
├── bin/
└── ScheduleJob/             # 示例：调度服务契约
    └── App/
        └── Module/
            └── ...          # Interface、Request、Response、DTO、Enum、Const
```

具体模块划分与命名以 [InterfaceApi.md](https://github.com/bingcool/swoolefy/blob/master/docs/InterfaceApi.md) 为准。

## 常用命令

在**本仓库根目录**先安装依赖（生成器依赖 `bingcool/swoolefy`）：

```bash
composer install
```

在**本仓库根目录**执行：

```bash
# §2 引用边界检查（默认检查 ScheduleJob 契约根，可传入路径）
php bin/reference-check.php
php bin/reference-check.php ScheduleJob

# 为指定服务契约生成 HTTP Client（XxxApiInterface → XxxApi）
php bin/generate-client.php --service=ScheduleJob/App

# 生成 OpenAPI 文档
php bin/generate-openapi.php --service=ScheduleJob/App
```

## 开发与协作说明

1. **契约变更**：修改 Interface / DTO / Request / Response 后，运行引用检查与 Client/OpenAPI 生成，并在各消费方升级 Composer 依赖版本。
2. **Support 变更**：`Support/` 为跨项目公共层，变更会影响所有消费方与生成器，需严格评审并保持与 Swoolefy 运行时兼容；详见 [Support/README.md](Support/README.md)。
3. **独立仓库**：本仓库与具体业务服务解耦，仅承载接口契约与工具链；业务实现、部署配置仍在各自服务仓库中维护。

## 包信息

| 项 | 值 |
|----|-----|
| Composer 包名 | `bingcool/interface-api` |
| 根命名空间 | `InterfaceApi\` |
| 许可证 | MIT |

## 相关链接

- [Swoolefy](https://github.com/bingcool/swoolefy)
- [InterfaceApi 规范](https://github.com/bingcool/swoolefy/blob/master/docs/InterfaceApi.md)
