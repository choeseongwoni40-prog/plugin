<?php
/**
 * WordPress 자동 포스팅 시스템
 * OpenAI API를 사용한 고품질 SEO 최적화 콘텐츠 자동 생성
 */

// 보안: 직접 접근 방지
if (!defined('ABSPATH')) {
    exit;
}

class WP_Auto_Poster {
    
    private $api_keys = [];
    private $current_key_index = 0;
    private $option_name = 'wp_auto_poster_settings';
    
    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('wp_auto_poster_cron', [$this, 'create_scheduled_post']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('wp_ajax_start_auto_posting', [$this, 'ajax_start_posting']);
        add_action('wp_ajax_stop_auto_posting', [$this, 'ajax_stop_posting']);
        add_action('wp_ajax_test_api_key', [$this, 'ajax_test_api_key']);
    }
    
    public function add_admin_menu() {
        add_menu_page(
            '자동 포스팅 설정',
            '자동 포스팅',
            'manage_options',
            'wp-auto-poster',
            [$this, 'render_settings_page'],
            'dashicons-edit',
            30
        );
    }
    
    public function register_settings() {
        register_setting('wp_auto_poster_group', $this->option_name);
    }
    
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'toplevel_page_wp-auto-poster') {
            return;
        }
        
        wp_enqueue_style('wp-auto-poster-admin', false);
        wp_add_inline_style('wp-auto-poster-admin', '
            .wrap { max-width: 1200px; }
            .auto-poster-card { background: #fff; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
            .auto-poster-card h2 { margin-top: 0; border-bottom: 2px solid #0073aa; padding-bottom: 10px; }
            .form-table th { width: 200px; }
            .api-key-row { margin-bottom: 10px; display: flex; gap: 10px; align-items: center; }
            .api-key-input { flex: 1; }
            .button-primary { background: #0073aa !important; border-color: #0073aa !important; }
            .status-active { color: #46b450; font-weight: bold; }
            .status-inactive { color: #dc3232; font-weight: bold; }
            .notice-success { border-left-color: #46b450; }
            .notice-error { border-left-color: #dc3232; }
        ');
        
        wp_enqueue_script('wp-auto-poster-admin', false, ['jquery'], '1.0', true);
        wp_add_inline_script('wp-auto-poster-admin', '
            jQuery(document).ready(function($) {
                $(";
        
        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'timeout' => 90,
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode([
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => '당신은 SEO와 애드센스 정책 전문가입니다. 고품질 정보성 콘텐츠를 작성하며, 완벽한 문법과 자연스러운 표현을 사용합니다.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'temperature' => 0.8,
                'max_tokens' => 3500
            ])
        ]);
        
        if (is_wp_error($response)) {
            error_log('WP Auto Poster API Error: ' . $response->get_error_message());
            return false;
        }
        
        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            error_log('WP Auto Poster: API 응답 코드 ' . $code);
            return false;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (empty($body['choices'][0]['message']['content'])) {
            error_log('WP Auto Poster: API 응답에 콘텐츠가 없습니다.');
            return false;
        }
        
        $content_json = $body['choices'][0]['message']['content'];
        $content_json = preg_replace('/```json\s*|\s*```/', '', trim($content_json));
        
        $content_data = json_decode($content_json, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('WP Auto Poster: JSON 파싱 오류 - ' . json_last_error_msg());
            return false;
        }
        
        if (empty($content_data['title']) || empty($content_data['content'])) {
            error_log('WP Auto Poster: 필수 콘텐츠 누락');
            return false;
        }
        
        $plain_text = strip_tags($content_data['content']);
        $char_count = mb_strlen(preg_replace('/\s+/', '', $plain_text));
        
        if ($char_count < 1500) {
            error_log("WP Auto Poster: 글자 수 부족 ({$char_count}자)");
            return false;
        }
        
        return $content_data;
    }
}

// 플러그인 초기화
function wp_auto_poster_init() {
    new WP_Auto_Poster();
}
add_action('init', 'wp_auto_poster_init');

// 활성화 훅
register_activation_hook(__FILE__, function() {
    $settings = get_option('wp_auto_poster_settings');
    if (!$settings) {
        update_option('wp_auto_poster_settings', [
            'topic' => '',
            'post_count' => 10,
            'category_id' => '',
            'post_status' => 'publish',
            'posts_created' => 0
        ]);
    }
});

// 비활성화 훅
register_deactivation_hook(__FILE__, function() {
    wp_clear_scheduled_hook('wp_auto_poster_cron');
});

// 삭제 훅
register_uninstall_hook(__FILE__, function() {
    delete_option('wp_auto_poster_settings');
    wp_clear_scheduled_hook('wp_auto_poster_cron');
});
?>#start-posting").on("click", function(e) {
                    e.preventDefault();
                    if (!confirm("자동 포스팅을 시작하시겠습니까?")) return;
                    
                    $.post(ajaxurl, {
                        action: "start_auto_posting",
                        nonce: "' . wp_create_nonce('auto_poster_nonce') . '"
                    }, function(response) {
                        alert(response.data.message);
                        location.reload();
                    });
                });
                
                $("#stop-posting").on("click", function(e) {
                    e.preventDefault();
                    if (!confirm("자동 포스팅을 중지하시겠습니까?")) return;
                    
                    $.post(ajaxurl, {
                        action: "stop_auto_posting",
                        nonce: "' . wp_create_nonce('auto_poster_nonce') . '"
                    }, function(response) {
                        alert(response.data.message);
                        location.reload();
                    });
                });
                
                $(".test-api-key").on("click", function(e) {
                    e.preventDefault();
                    var button = $(this);
                    var input = button.prev("input");
                    var apiKey = input.val();
                    
                    if (!apiKey) {
                        alert("API 키를 입력하세요.");
                        return;
                    }
                    
                    button.prop("disabled", true).text("테스트 중...");
                    
                    $.post(ajaxurl, {
                        action: "test_api_key",
                        nonce: "' . wp_create_nonce('auto_poster_nonce') . '",
                        api_key: apiKey
                    }, function(response) {
                        alert(response.data.message);
                        button.prop("disabled", false).text("테스트");
                    }).fail(function() {
                        alert("테스트 실패");
                        button.prop("disabled", false).text("테스트");
                    });
                });
            });
        ');
    }
    
    public function render_settings_page() {
        $settings = get_option($this->option_name, $this->get_default_settings());
        $is_active = wp_next_scheduled('wp_auto_poster_cron') ? true : false;
        $next_run = wp_next_scheduled('wp_auto_poster_cron');
        ?>
        <div class="wrap">
            <h1>🚀 자동 포스팅 설정</h1>
            
            <div class="auto-poster-card">
                <h2>📊 현재 상태</h2>
                <p>
                    <strong>포스팅 상태:</strong> 
                    <span class="<?php echo $is_active ? 'status-active' : 'status-inactive'; ?>">
                        <?php echo $is_active ? '● 활성화' : '● 비활성화'; ?>
                    </span>
                </p>
                <?php if ($next_run): ?>
                <p><strong>다음 포스팅:</strong> <?php echo date('Y-m-d H:i:s', $next_run); ?></p>
                <?php endif; ?>
                
                <p>
                    <?php if ($is_active): ?>
                        <button id="stop-posting" class="button button-secondary">⏸ 포스팅 중지</button>
                    <?php else: ?>
                        <button id="start-posting" class="button button-primary">▶ 포스팅 시작</button>
                    <?php endif; ?>
                </p>
            </div>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('wp_auto_poster_group');
                ?>
                
                <div class="auto-poster-card">
                    <h2>🔑 OpenAI API 키 설정 (최대 7개)</h2>
                    <table class="form-table">
                        <tr>
                            <th>API 키</th>
                            <td>
                                <?php for ($i = 1; $i <= 7; $i++): ?>
                                <div class="api-key-row">
                                    <input type="text" 
                                           name="<?php echo $this->option_name; ?>[api_key_<?php echo $i; ?>]" 
                                           value="<?php echo esc_attr($settings['api_key_' . $i] ?? ''); ?>" 
                                           class="regular-text api-key-input"
                                           placeholder="sk-proj-...">
                                    <button type="button" class="button test-api-key">테스트</button>
                                    <span>키 <?php echo $i; ?></span>
                                </div>
                                <?php endfor; ?>
                                <p class="description">OpenAI API 키를 입력하세요. 여러 키를 설정하면 순환하며 사용합니다.</p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <div class="auto-poster-card">
                    <h2>📝 콘텐츠 설정</h2>
                    <table class="form-table">
                        <tr>
                            <th>포스팅 주제</th>
                            <td>
                                <input type="text" 
                                       name="<?php echo $this->option_name; ?>[topic]" 
                                       value="<?php echo esc_attr($settings['topic'] ?? ''); ?>" 
                                       class="regular-text" 
                                       placeholder="예: 건강한 식습관, 재테크 방법, 프로그래밍 튜토리얼">
                                <p class="description">모든 글이 이 주제를 기반으로 생성됩니다.</p>
                            </td>
                        </tr>
                        <tr>
                            <th>생성할 글 개수</th>
                            <td>
                                <input type="number" 
                                       name="<?php echo $this->option_name; ?>[post_count]" 
                                       value="<?php echo esc_attr($settings['post_count'] ?? 10); ?>" 
                                       min="1" 
                                       max="100" 
                                       class="small-text">
                                <p class="description">1~100개까지 설정 가능합니다.</p>
                            </td>
                        </tr>
                        <tr>
                            <th>포스팅 카테고리</th>
                            <td>
                                <?php
                                $categories = get_categories(['hide_empty' => false]);
                                ?>
                                <select name="<?php echo $this->option_name; ?>[category_id]">
                                    <option value="">선택하세요</option>
                                    <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat->term_id; ?>" 
                                            <?php selected($settings['category_id'] ?? '', $cat->term_id); ?>>
                                        <?php echo esc_html($cat->name); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th>포스팅 상태</th>
                            <td>
                                <select name="<?php echo $this->option_name; ?>[post_status]">
                                    <option value="publish" <?php selected($settings['post_status'] ?? 'publish', 'publish'); ?>>즉시 발행</option>
                                    <option value="draft" <?php selected($settings['post_status'] ?? '', 'draft'); ?>>임시 저장</option>
                                </select>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <?php submit_button('설정 저장'); ?>
            </form>
            
            <div class="auto-poster-card">
                <h2>ℹ️ 사용 안내</h2>
                <ol>
                    <li>OpenAI API 키를 하나 이상 입력하세요 (최대 7개까지 로테이션)</li>
                    <li>포스팅 주제와 개수를 설정하세요</li>
                    <li>"설정 저장" 버튼을 클릭하세요</li>
                    <li>"포스팅 시작" 버튼을 클릭하면 자동으로 글이 생성됩니다</li>
                    <li>글은 1시간~1시간 10분 간격으로 자동 발행됩니다</li>
                </ol>
                <p><strong>⚠️ 주의사항:</strong></p>
                <ul>
                    <li>모든 글은 1500자 이상의 고품질 SEO 최적화 콘텐츠로 생성됩니다</li>
                    <li>애드센스 정책을 완벽히 준수하는 정보성 콘텐츠입니다</li>
                    <li>API 사용량을 최소화하기 위해 최적화되어 있습니다</li>
                </ul>
            </div>
        </div>
        <?php
    }
    
    private function get_default_settings() {
        return [
            'topic' => '',
            'post_count' => 10,
            'category_id' => '',
            'post_status' => 'publish',
            'posts_created' => 0
        ];
    }
    
    public function ajax_start_posting() {
        check_ajax_referer('auto_poster_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => '권한이 없습니다.']);
        }
        
        $settings = get_option($this->option_name);
        
        if (empty($settings['topic'])) {
            wp_send_json_error(['message' => '포스팅 주제를 설정해주세요.']);
        }
        
        $this->load_api_keys();
        if (empty($this->api_keys)) {
            wp_send_json_error(['message' => 'API 키를 하나 이상 설정해주세요.']);
        }
        
        // 기존 스케줄 제거
        wp_clear_scheduled_hook('wp_auto_poster_cron');
        
        // 초기화
        $settings['posts_created'] = 0;
        update_option($this->option_name, $settings);
        
        // 첫 포스팅 즉시 실행
        $this->create_scheduled_post();
        
        wp_send_json_success(['message' => '자동 포스팅이 시작되었습니다!']);
    }
    
    public function ajax_stop_posting() {
        check_ajax_referer('auto_poster_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => '권한이 없습니다.']);
        }
        
        wp_clear_scheduled_hook('wp_auto_poster_cron');
        wp_send_json_success(['message' => '자동 포스팅이 중지되었습니다.']);
    }
    
    public function ajax_test_api_key() {
        check_ajax_referer('auto_poster_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => '권한이 없습니다.']);
        }
        
        $api_key = sanitize_text_field($_POST['api_key'] ?? '');
        
        if (empty($api_key)) {
            wp_send_json_error(['message' => 'API 키를 입력하세요.']);
        }
        
        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode([
                'model' => 'gpt-3.5-turbo',
                'messages' => [['role' => 'user', 'content' => 'test']],
                'max_tokens' => 10
            ])
        ]);
        
        if (is_wp_error($response)) {
            wp_send_json_error(['message' => 'API 연결 실패: ' . $response->get_error_message()]);
        }
        
        $code = wp_remote_retrieve_response_code($response);
        
        if ($code === 200) {
            wp_send_json_success(['message' => '✅ API 키가 정상적으로 작동합니다!']);
        } else {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            $error_msg = $body['error']['message'] ?? '알 수 없는 오류';
            wp_send_json_error(['message' => '❌ API 키 오류: ' . $error_msg]);
        }
    }
    
    public function create_scheduled_post() {
        $settings = get_option($this->option_name);
        
        // 설정된 개수만큼 생성했는지 확인
        if ($settings['posts_created'] >= $settings['post_count']) {
            wp_clear_scheduled_hook('wp_auto_poster_cron');
            return;
        }
        
        $this->load_api_keys();
        
        if (empty($this->api_keys)) {
            error_log('WP Auto Poster: API 키가 설정되지 않았습니다.');
            return;
        }
        
        // 콘텐츠 생성
        $content_data = $this->generate_content($settings['topic'], $settings['posts_created'] + 1);
        
        if (!$content_data) {
            error_log('WP Auto Poster: 콘텐츠 생성 실패');
            // 다음 시도를 위해 스케줄 재설정
            $this->schedule_next_post();
            return;
        }
        
        // 포스트 생성
        $post_data = [
            'post_title' => sanitize_text_field($content_data['title']),
            'post_content' => wp_kses_post($content_data['content']),
            'post_status' => $settings['post_status'] ?? 'publish',
            'post_author' => 1,
            'post_type' => 'post'
        ];
        
        if (!empty($settings['category_id'])) {
            $post_data['post_category'] = [$settings['category_id']];
        }
        
        $post_id = wp_insert_post($post_data);
        
        if (is_wp_error($post_id)) {
            error_log('WP Auto Poster: 포스트 생성 실패 - ' . $post_id->get_error_message());
            $this->schedule_next_post();
            return;
        }
        
        // SEO 메타 데이터 추가
        if (!empty($content_data['meta_description'])) {
            update_post_meta($post_id, '_yoast_wpseo_metadesc', $content_data['meta_description']);
        }
        
        // 생성된 포스트 개수 증가
        $settings['posts_created']++;
        update_option($this->option_name, $settings);
        
        error_log("WP Auto Poster: 포스트 생성 완료 (ID: $post_id) - {$settings['posts_created']}/{$settings['post_count']}");
        
        // 다음 포스트 스케줄링
        if ($settings['posts_created'] < $settings['post_count']) {
            $this->schedule_next_post();
        }
    }
    
    private function schedule_next_post() {
        // 1시간(3600초) ~ 1시간 10분(4200초) 사이의 랜덤 시간
        $next_time = time() + rand(3600, 4200);
        wp_schedule_single_event($next_time, 'wp_auto_poster_cron');
    }
    
    private function load_api_keys() {
        $settings = get_option($this->option_name);
        $this->api_keys = [];
        
        for ($i = 1; $i <= 7; $i++) {
            if (!empty($settings['api_key_' . $i])) {
                $this->api_keys[] = $settings['api_key_' . $i];
            }
        }
    }
    
    private function get_next_api_key() {
        if (empty($this->api_keys)) {
            return null;
        }
        
        $key = $this->api_keys[$this->current_key_index];
        $this->current_key_index = ($this->current_key_index + 1) % count($this->api_keys);
        
        return $key;
    }
    
    private function generate_content($topic, $post_number) {
        $api_key = $this->get_next_api_key();
        
        if (!$api_key) {
            return false;
        }
        
        // 최적화된 프롬프트 (크레딧 최소화)
        $prompt = "주제: {$topic}

다음 요구사항을 만족하는 블로그 글을 작성해주세요:

1. 제목: SEO 최적화된 40-60자 제목
2. 본문: 1500자 이상의 정보성 콘텐츠
3. 구조: 서론, 본론(소제목 3-5개), 결론
4. SEO: 자연스러운 키워드 배치
5. 스타일: 전문적이고 읽기 쉬운 한국어
6. 독창성: 이전 글과 완전히 다른 각도와 내용
7. 애드센스 정책 준수: 정확한 정보, 가치 제공

글 번호: {$post_number}

응답 형식 (JSON):
{
  \"title\": \"글 제목\",
  \"content\": \"HTML 형식의 본문 (<h2>, <h3>, <p>, <ul>, <li> 사용)\",
  \"meta_description\": \"150자 이내 메타 설명\"
}";

        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'timeout' => 60,
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode([
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => '당신은 SEO와 애드센스 정책에 정통한 전문 콘텐츠 작가입니다. 고품질의 정보성 콘텐츠를 작성하며, 문법과 맞춤법이 완벽하고, 사람이 쓴 것처럼 자연스러운 글을 작성합니다.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'temperature' => 0.8,
                'max_tokens' => 3000
            ])
        ]);
        
        if (is_wp_error($response)) {
            error_log('WP Auto Poster API Error: ' . $response->get_error_message());
            return false;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (empty($body['choices'][0]['message']['content'])) {
            error_log('WP Auto Poster: API 응답에 콘텐츠가 없습니다.');
            return false;
        }
        
        $content_json = $body['choices'][0]['message']['content'];
        
        // JSON 파싱
        $content_json = preg_replace('/```json\s*|\s*```/', '', $content_json);
        $content_data = json_decode($content_json, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('WP Auto Poster: JSON 파싱 오류 - ' . json_last_error_msg());
            return false;
        }
        
        // 콘텐츠 검증
        if (empty($content_data['title']) || empty($content_data['content'])) {
            error_log('WP Auto Poster: 필수 콘텐츠가 누락되었습니다.');
            return false;
        }
        
        // 글자 수 확인 (HTML 태그 제외)
        $plain_text = strip_tags($content_data['content']);
        $char_count = mb_strlen(str_replace(' ', '', $plain_text));
        
        if ($char_count < 1500) {
            error_log("WP Auto Poster: 글자 수 부족 ({$char_count}자)");
            return false;
        }
        
        return $content_data;
    }
}

// 플러그인 초기화
function wp_auto_poster_init() {
    new WP_Auto_Poster();
}
add_action('plugins_loaded', 'wp_auto_poster_init');

// 플러그인 활성화 시 실행
register_activation_hook(__FILE__, function() {
    // Cron 이벤트 등록
    if (!wp_next_scheduled('wp_auto_poster_cron')) {
        wp_schedule_single_event(time() + 60, 'wp_auto_poster_cron');
    }
});

// 플러그인 비활성화 시 실행
register_deactivation_hook(__FILE__, function() {
    // Cron 이벤트 제거
    wp_clear_scheduled_hook('wp_auto_poster_cron');
});
?>
