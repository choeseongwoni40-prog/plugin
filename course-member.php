<?php
/**
 * 프레스런 통합 강의 수강생 자동 설정
 */

if (!defined('ABSPATH')) {
    exit;
}

// 플러그인 로드 시 한 번만 실행
add_action('plugins_loaded', function() {
    // 강의 수강생 옵션 설정
    if (get_option('presslearn_course_member') !== 'yes') {
        update_option('presslearn_course_member', 'yes');
    }
    if (get_option('alpack_course_member') !== 'yes') {
        update_option('alpack_course_member', 'yes');
    }
}, 1);

// 옵션 필터 - 항상 'yes' 반환
add_filter('option_presslearn_course_member', function($value) {
    return 'yes';
}, 10);

add_filter('option_alpack_course_member', function($value) {
    return 'yes';
}, 10);

// 강의 수강생 체크 필터
add_filter('presslearn_is_course_member', '__return_true', 10);
add_filter('alpack_is_course_member', '__return_true', 10);
