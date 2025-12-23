<?php

namespace App\Http\Responses;

use App\Enum\AuthUserType;
use App\Services\PageExcel;
use App\Services\PaginateService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Request;
use Illuminate\View\View;

final class ResponseBuilder
{
    private array|object|null $data     = null;
    private array $messages             = [];
    private array $extras               = [];
    private array $lang                 = [];
    private mixed $option               = null;
    private ?string $view               = null;
    private array $viewParams           = [];
    private ?RedirectResponse $redirect = null;

    public function __construct(public string $controller_class, public ?\Illuminate\Http\Request $request = null)
    {
        // 默认语言取当前 App locale
        if (!$this->request) {
            $this->request = request();
        }
    }

    /**
     * 设置 data（可以多次调用覆盖或追加）.
     */
    public function withData(array|object|null $data): self
    {
        // 如果是分页，就自动从里面提取 option
        if ($data instanceof PaginateService) {
            $this->data   = $data->paginator ?? $data;
            $this->option = $data->getParam();
        } else {
            $this->data = $data;
        }

        return $this;
    }

    /**
     * 设置提示信息.
     *
     * @param null|mixed $messageType
     */
    public function withMessages(array|string $message, $messageType = null): self
    {
        if ($this->request->wantsJson()) {
            if (is_array($message)) {
                $this->messages[] = $message;
            } else {
                $this->messages[] = [$message, $messageType];
            }
        }

        return $this;
    }

    /**
     * 设置额外字段，替换原数组.
     */
    public function withExtras(...$extras): self
    {
        foreach ($extras as $extra) {
            $this->extras += $extra;
        }

        return $this;
    }

    /**
     * 设置额外字段，替换原数组.
     *
     * @param mixed $extra
     */
    public function withExtra($extra): self
    {
        $this->extras += $extra;

        return $this;
    }

    /**
     * 合并额外字段（追加到已有的 extras）.
     */
    public function appendExtras(array $more): self
    {
        $this->extras = array_merge($this->extras, $more);

        return $this;
    }

    public function withLang(...$langs): self
    {
        foreach ($langs as $lang) {
            $this->lang += $lang;
        }

        //        $this->lang += $lang;

        return $this;
    }

    public function withOption(mixed $option): self
    {
        $this->option = $option;

        return $this;
    }

    /**
     * 指定要渲染的视图和参数.
     */
    public function withView(string $view, array $params = []): self
    {
        $this->view       = $view;
        $this->viewParams = $params;

        return $this;
    }

    /**
     * 最终响应.
     */
    public function respond(int $status = 200): JsonResponse|RedirectResponse|Response|View
    {
        if ($wantsJson = $this->request->wantsJson()) {
            if (PageExcel::check_request($this->request) && $this->data instanceof PaginateService) {
                $pageExcel = new PageExcel($this->request->route()->getActionName());

                $export = $pageExcel->export($this->data->builder, $this->data->columns);

                $this->withData($export);
            }

            return response()->json($this->payload(), $status);
        }

        if ($this->redirect) {
            return $this->redirect;
        }

        $this->view = get_view_file($this->request);
        $payload    = array_merge(
            $this->payload(),
            $this->viewParams
        );

        return response()->view($this->view, $payload, $status);
    }

    public function withRedirect(RedirectResponse $back)
    {
        $this->redirect = $back;

        return $this;
    }

    private function payload(): array
    {
        return [
            'data'     => $this->data,
            'message'  => $this->messages[0][0] ?? null,
            'messages' => $this->messages,
            'extra'    => $this->extras,
            'lang'     => $this->lang,
            'option'   => $this->option,
            'meta'     => [
                'method'         => Request::method(),
                'url'            => Request::fullUrl(),
                'ip'             => Request::ip(),
                'timestamp'      => now()->toIso8601String(),
                'class_basename' => $this->data instanceof Model ? class_basename(get_class($this->data)) : null,
                'table'          => $this->data instanceof Model ? $this->data->getTable() : null,
                'auth_user'      => AuthUserType::getValue(),
            ],
        ];
    }
}
