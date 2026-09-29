# InterfaceApi JSON / SSE / Chunked / Download Client 完整闭环修复方案

## 1. 文档结论

当前 `bingcool/interface-api-service` 已经具备 JSON / SSE / Chunked / Download 四类响应的 **BaseClientApi 解析能力**。

当前状态不是重新设计四种响应，而是补齐：

```text
Interface 注解
      ↓
Client Generator
      ↓
Generated Client
      ↓
BaseClientApi
      ↓
对应 Response Parser
```

当前状态：

```text
BaseClientApi
├── JSON       ✅ 已实现
├── SSE        ✅ 已实现
├── Chunked    ✅ 已实现
└── Download   ✅ 已实现

Interface / Annotation
├── StreamResponse       ✅ 已实现
├── ChunkedResponse      ✅ 已实现
└── DownloadResponse     ✅ 已实现

Client Generator
└── 特殊响应注解 → 对应 Parser 的生成映射
                         ⚠️ 需要补齐
```

因此本次修复应保持现有架构，仅补齐：

1. `ClientWriter` 对三种特殊响应注解的识别。
2. 特殊响应方法的返回类型校验。
3. 特殊响应请求参数构造。
4. 特殊响应生成代码调用正确 Parser。
5. 三种特殊响应不进入 `CovertProperty` 和业务 `code` 校验。
6. 增加生成器与 `BaseClientApi` 的端到端回归测试。

---

# 2. 当前实际实现

仓库：

```text
bingcool/interface-api-service
```

Composer 包：

```text
bingcool/interface-api
```

根命名空间：

```text
InterfaceApi\\
```

公共 Support：

```text
Support/
```

Client Generator：

```text
bin/generate-client.php
Support/Generator/ClientGenerator.php
Support/Generator/ClientWriter.php
```

生成器在契约仓库内部执行，不再依赖业务服务 Router 生成 SDK。

例如：

```bash
php bin/generate-client.php --service=ScheduleJob/App
```

---

# 3. 四类响应当前已经具备的能力

## 3.1 JSON

当前 `BaseClientApi` 已经具备：

```text
parseJsonResponse()
parseJsonResponseWithBusinessOk()
assertHttpOk()
assertBusinessOk()
```

处理流程：

```text
HTTP Response
     ↓
HTTP 2xx 检查
     ↓
读取 Body
     ↓
json_decode
     ↓
业务 code 校验
     ↓
payload
     ↓
CovertProperty
     ↓
Response DTO
```

---

## 3.2 SSE

当前 `BaseClientApi` 已经具备：

```text
parseSseResponse()
decodeSseEvents()
```

支持：

```text
event
id
data
```

并支持多行 `data`、SSE comment/heartbeat，以及 JSON `data` 自动 decode。

返回结构：

```php
list<array{
    event: string,
    id: ?string,
    data: mixed
}>
```

处理流程：

```text
HTTP Response
     ↓
HTTP 2xx
     ↓
text/event-stream
     ↓
SSE decode
     ↓
event list
```

不经过：

```text
business code
CovertProperty
Response DTO
```

---

## 3.3 Chunked

当前已经具备：

```text
parseStreamResponse()
```

返回：

```php
string
```

当前语义：Client 返回完整原始 body，由调用方自行按业务协议继续处理。

---

## 3.4 Download

当前已经具备：

```text
parseDownloadResponse()
extractDownloadFilename()
```

返回：

```php
array{
    content: string,
    filename: ?string,
    contentType: ?string
}
```

支持：

```text
Content-Disposition: attachment
Content-Disposition: inline
filename=
filename*=UTF-8''
```

不进入 JSON decode、业务 code、`CovertProperty`。

---

# 4. 当前真正未完全闭环的位置

当前 `ClientWriter::emitClientMethod()` 仍然主要按照普通 JSON API 生成 Client。

也就是说 Support 层已经准备好了，但 Generator 还需要真正将：

```text
#[StreamResponse]
#[ChunkedResponse]
#[DownloadResponse]
```

映射成不同的生成模板。

当前状态准确描述为：

```text
Parser
✅

Annotation
✅

Generator Mapping
⚠️

Generated Client
⚠️ 当前仍主要按 JSON 生成
```

---

# 5. 最终 Response Mode

生成器统一得到：

```text
json
sse
chunked
download
```

规则：

```text
0 个特殊响应注解
    → json

1 个 StreamResponse
    → sse

1 个 ChunkedResponse
    → chunked

1 个 DownloadResponse
    → download

超过 1 个
    → GeneratorException
```

建议新增：

```php
private function resolveResponseMode(ReflectionMethod $method): string
```

示例：

```php
private function resolveResponseMode(ReflectionMethod $method): string
{
    $modes = [];

    if ($method->getAttributes(StreamResponse::class) !== []) {
        $modes[] = 'sse';
    }

    if ($method->getAttributes(ChunkedResponse::class) !== []) {
        $modes[] = 'chunked';
    }

    if ($method->getAttributes(DownloadResponse::class) !== []) {
        $modes[] = 'download';
    }

    if (count($modes) > 1) {
        throw new GeneratorException(
            $method->getName() . '(): only one response mode is allowed'
        );
    }

    return $modes[0] ?? 'json';
}
```

---

# 6. 返回类型校验

生成阶段严格限制：

| Response Mode | PHP 返回类型 |
|---|---|
| json | `BaseResponse` 子类 / `void` |
| sse | `array` |
| chunked | `string` |
| download | `array` |

特殊响应不能声明：

```php
void
```

例如：

```php
#[StreamResponse]
public function stream(): string;
```

必须失败。

```php
#[ChunkedResponse]
public function stream(): ChatResponse;
```

必须失败。

```php
#[DownloadResponse]
public function download(): ChatResponse;
```

必须失败。

---

# 7. JSON Client 生成规则

无特殊响应注解时，继续使用普通 JSON Client。

例如：

```php
$response = $this->requestWithConnectRetry(
    'POST',
    $this->uri('/api/v1/chat'),
    $options,
);

$result = $this->parseResponseByHeaders($response);

return CovertProperty::toCovertDeepProperty(
    $result,
    ChatResponse::class,
);
```

返回：

```php
ChatResponse
```

或者：

```php
void
```

---

# 8. SSE Client 生成规则

接口：

```php
#[StreamResponse]
#[Route(method: 'POST', path: '/v1/chat/stream')]
public function chatStream(
    ChatStreamRequest $request,
): array;
```

生成器校验：

```text
StreamResponse → array
```

请求使用：

```text
mergeStreamClientOptions()
```

并设置：

```http
Accept: text/event-stream
```

生成：

```php
$options = $this->mergeStreamClientOptions(
    $requestDefaults,
    $options,
);

$response = $this->requestWithConnectRetry(
    'POST',
    $this->uri('/v1/chat/stream'),
    $options,
);

return $this->parseResponseByHeaders(
    $response,
    'sse',
);
```

不允许：

```text
CovertProperty
assertBusinessOk
JSON Response DTO
```

---

# 9. Chunked Client 生成规则

接口：

```php
#[ChunkedResponse]
#[Route(method: 'GET', path: '/v1/export')]
public function export(
    ExportRequest $request,
): string;
```

生成：

```php
$options = $this->mergeStreamClientOptions(
    $requestDefaults,
    $options,
);

$response = $this->requestWithConnectRetry(
    'GET',
    $this->uri('/v1/export'),
    $options,
);

return $this->parseResponseByHeaders(
    $response,
    'chunked',
);
```

最终返回：

```php
string
```

不做 JSON decode、业务 code 校验和 DTO 转换。

---

# 10. Download Client 生成规则

接口：

```php
#[DownloadResponse]
#[Route(method: 'GET', path: '/v1/file')]
public function download(
    DownloadRequest $request,
): array;
```

生成：

```php
$options = $this->mergeClientOptions(
    $requestDefaults,
    $options,
);

$response = $this->requestWithConnectRetry(
    'GET',
    $this->uri('/v1/file'),
    $options,
);

return $this->parseResponseByHeaders(
    $response,
    'download',
);
```

返回：

```php
array{
    content: string,
    filename: ?string,
    contentType: ?string
}
```

---

# 11. 请求参数规则

四类响应共享现有 HTTP Method → Request 参数规则：

```text
GET
HEAD
DELETE
OPTIONS
    ↓
query

POST
PUT
PATCH
    ↓
JSON body
```

特殊响应只改变 Response 处理方式，不改变 Request 参数编码规则。

例如：

```php
#[StreamResponse]
#[Route(method: 'POST', path: '/chat/stream')]
```

仍然使用：

```php
$request->toDeepArray()
```

生成 JSON body。

---

# 12. `parseResponseByHeaders()` 继续作为统一入口

当前 `BaseClientApi` 已经提供：

```php
parseResponseByHeaders(
    ResponseInterface $response,
    ?string $forceType = null,
)
```

保留这一入口，不需要重新设计 Parser 架构。

其逻辑：

```text
forceType
    ↓
┌───────────┬───────────┬────────────┬────────┐
│ sse       │ chunked   │ download   │ json   │
└───────────┴───────────┴────────────┴────────┘
```

并继续保留现有 Header 自动识别能力作为 fallback。

原则：

```text
Interface 注解
    优先
      ↓
Header Detection
    fallback
```

契约是编译期明确类型，HTTP Header 是运行期结果，因此 Generated Client 有明确 Response Mode 时优先使用 `forceType`。

---

# 13. SSE 请求 Header

SSE 必须使用：

```http
Accept: text/event-stream
```

因此 Generator 应在生成 SSE Client 时自动写入：

```php
$requestDefaults['headers']['Accept'] = 'text/event-stream';
```

或者在 `mergeStreamClientOptions()` 中通过请求默认值完成。

不要要求每个业务开发者手动加入 Header。

---

# 14. 普通 JSON / Stream Options 的边界

普通 JSON：

```text
mergeClientOptions()
```

Stream / Chunked：

```text
mergeStreamClientOptions()
```

这样可以避免流式接口错误携带默认：

```http
Content-Type: application/json
```

下载通常继续使用：

```text
mergeClientOptions()
```

但不进入 JSON Parser。

---

# 15. Generator 主要修改点

主要文件：

```text
Support/Generator/ClientWriter.php
```

建议增加：

```text
resolveResponseMode()
validateResponseType()
```

并让：

```text
emitClientMethod()
```

接收：

```text
responseMode
```

示意：

```php
$responseMode = $this->resolveResponseMode($method);
$this->validateResponseType($method, $responseMode);

$methodBlocks[] = $this->emitClientMethod(
    ...,
    responseMode: $responseMode,
);
```

不要重新改动 `BaseClientApi` 的已有四种 Parser。

---

# 16. `void` 特殊处理

只有普通 JSON 支持：

```php
public function ping(): void;
```

生成：

```php
$response = ...;
$this->parseResponseByHeaders($response);
return;
```

特殊响应必须有对应的数据类型：

```text
SSE + void
❌

Chunked + void
❌

Download + void
❌
```

---

# 17. 四类响应最终完整链路

```text
                    InterfaceApi
                         │
                         │
              #[Route]
              #[StreamResponse]
              #[ChunkedResponse]
              #[DownloadResponse]
                         │
                         ↓
                  Client Generator
                         │
                         ↓
                 Generated Client
                         │
                         ↓
                 BaseClientApi
                         │
              ┌──────────┼──────────┐
              │          │          │
              ↓          ↓          ↓
            Nacos      Guzzle     Headers
                         │
                         ↓
                    HTTP Response
                         │
                         ↓
              parseResponseByHeaders
                         │
       ┌─────────────────┼──────────────────┐
       ↓                 ↓                  ↓
      JSON              SSE             Chunked
       │                 │                  │
       ↓                 ↓                  ↓
 business code      event parser       raw body
       │                 │                  │
       ↓                 ↓                  ↓
 CovertProperty       array              string
       │
       ↓
 Response DTO

                    Download
                       │
                       ↓
              content / filename
              contentType
```

---

# 18. 测试要求

## 18.1 JSON

至少验证：

```text
200 + code=0
200 + code!=0
非 2xx
非法 JSON
嵌套 DTO
ArrayList
```

---

## 18.2 SSE

至少验证：

```text
StreamResponse
→ Generator
→ Accept: text/event-stream
→ SSE Parser
→ events[]
```

内容覆盖：

```text
event
id
data
多行 data
comment / heartbeat
JSON data
普通字符串 data
```

---

## 18.3 Chunked

验证：

```text
ChunkedResponse
→ Generator
→ raw body
→ string
```

必须确保：

```text
不会 JSON decode
不会 business code
不会 CovertProperty
```

---

## 18.4 Download

验证：

```text
DownloadResponse
→ Generator
→ binary body
→ filename
→ contentType
```

覆盖：

```text
filename="a.txt"
filename*=UTF-8''中文.txt
无 Content-Disposition
Content-Type
```

---

# 19. Generator 失败测试

必须覆盖：

```text
StreamResponse + ChatResponse
    → fail

ChunkedResponse + ChatResponse
    → fail

DownloadResponse + ChatResponse
    → fail

StreamResponse + ChunkedResponse
    → fail

StreamResponse + DownloadResponse
    → fail

ChunkedResponse + DownloadResponse
    → fail

三个同时存在
    → fail

StreamResponse + void
    → fail

ChunkedResponse + void
    → fail

DownloadResponse + void
    → fail
```

---

# 20. 不在本次范围内

本次只完成：

```text
Interface
    ↓
Generator
    ↓
Generated Client
    ↓
BaseClientApi
    ↓
Response Parser
```

不新增：

```text
独立 StreamClient 抽象
新的 HTTP Client 框架
新的 RPC 协议
服务端 Router 自动生成
Service Mesh
熔断系统
复杂流式缓存
```

保持 `interface-api-service` 的当前定位：

> 公共 API 契约 + Client Generator + Client Runtime Support。

---

# 21. Retry 独立问题

当前 `BaseClientApi::requestWithConnectRetry()` 使用：

```php
catch (RequestException $e)
```

`RequestException` 不只代表连接异常，也可能包含 HTTP 4xx/5xx。

因此存在：

```text
HTTP 500
   ↓
RequestException
   ↓
connect retry
```

建议后续单独调整为：

```text
ConnectException / Transport failure
        ↓
Connect Retry

HTTP 4xx / 5xx
        ↓
直接进入 HTTP Error 处理
```

这个属于 Retry 边界问题，不和本次四类 Response 闭环修复混合。

---

# 22. 最终实施顺序

```text
P0
────────────────────────────
① ClientWriter 增加 response mode 解析

② 增加特殊响应返回类型校验

③ 生成 SSE Client

④ 生成 Chunked Client

⑤ 生成 Download Client


P1
────────────────────────────
⑥ Generator 回归测试

⑦ JSON / SSE / Chunked / Download
   端到端测试

⑧ Retry 异常边界单独修复
```

---

# 23. 最终结论

当前 `BaseClientApi` 已经完成：

```text
JSON       ✅
SSE        ✅
Chunked    ✅
Download   ✅
```

当前真正剩余的是：

```text
Client Generator
      ↓
将响应注解映射到正确 Parser
```

因此本次不是重新实现四种协议，而是完成最后的：

```text
契约
 ↓
生成器
 ↓
Client
 ↓
BaseClientApi
 ↓
Parser
```

完成后，`interface-api-service` 的 JSON / SSE / Chunked / Download Client 能力才真正形成完整闭环。
