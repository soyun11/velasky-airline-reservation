<?php
session_start(); // 세션 시작 (현재 로그인 상태를 유지하는 세션 접근)


// 1. 세션 변수 모두 초기화
$_SESSION = []; // 세션 배열을 빈 배열로 만들어 모든 세션 데이터를 제거

// 2. 세션 쿠키 삭제
if (ini_get("session.use_cookies")) { // 세션이 쿠키를 사용한다면
    $params = session_get_cookie_params(); // 현재 세션 쿠키 설정을 가져옴
    setcookie(session_name(), '', time() - 42000, // 세션 쿠키의 만료 시간을 과거로 설정해 삭제
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 3. 세션 완전 파기
session_destroy(); // 서버에 저장된 세션 자체를 제거

// 4. 로그아웃 후 메인 페이지로 리다이렉트
header("Location: ../php/main.php"); // 메인 페이지로 이동
exit; // 이후 코드 실행 방지
?>
