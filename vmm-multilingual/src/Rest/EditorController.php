<?php
declare(strict_types=1);

namespace VMM\Multilingual\Rest;

use VMM\Multilingual\Integrations\Yootheme\EditorService;

final class EditorController
{
    public function __construct(private readonly EditorService $editor)
    {
    }

    public function register(): void
    {
        add_action('rest_api_init', function (): void {
            foreach (['/editor/page/(?P<id>\d+)/open' => 'POST', '/editor/page/(?P<id>\d+)' => 'PUT'] as $route => $method) {
                register_rest_route('vmm-multilingual/v1', $route, [
                    'methods' => $method,
                    'permission_callback' => static fn(\WP_REST_Request $request): bool => get_post_type((int) $request['id']) === 'page' && current_user_can('edit_post', (int) $request['id']),
                    'callback' => function (\WP_REST_Request $request) use ($method): array|\WP_Error {
                        $data = $request->get_json_params();
                        if (!is_array($data) || !in_array($data['locale'] ?? '', array_keys(\VMM\Multilingual\Site\Languages::all()), true)) {
                            return new \WP_Error('vmm_locale', 'Ungültige Sprache.', ['status' => 400]);
                        }
                        if (strlen($request->get_body()) > 5 * 1024 * 1024) {
                            return new \WP_Error('vmm_size', 'Layout ist zu groß.', ['status' => 413]);
                        }
                        try {
                            return $method === 'POST' ? $this->editor->open((int) $request['id'], $data['locale']) : $this->editor->save((int) $request['id'], $data);
                        } catch (\Throwable $error) {
                            return new \WP_Error('vmm_save', $error->getMessage(), ['status' => 409]);
                        }
                    },
                ]);
            }
        });
    }
}
