<?php
session_start(); // 세션 시작: 로그인된 사용자 정보를 유지하기 위해 세션 사용

// 세션에 저장된 로그인 사용자 정보 가져오기
$cno = $_SESSION['cno'];              // 회원번호
$name = $_SESSION['name'];            // 이름
$passwd = $_SESSION['passwd'];        // 비밀번호
$email = $_SESSION['email'];          // 이메일
$passport = $_SESSION['passportNumber']; // 여권 번호
?>

<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8"> <!-- 문서 인코딩 설정 (한글 깨짐 방지) -->
  <title>회원 정보</title> <!-- 브라우저 탭 제목 -->
  <link rel="stylesheet" href="../css/mypage.css"> <!-- CSS 스타일 적용 -->
</head>
<body>
  <div class="container"> <!-- 전체 콘텐츠를 감싸는 컨테이너 -->

    <!-- 상단 헤더 영역 -->
    <header>
      <a href="../php/main.php" class="logo">Vela Sky</a> <!-- 로고 클릭 시 메인 페이지로 이동 -->
      <span class="page-title">회원</span> <!-- 페이지 제목 -->
    </header>

    <!-- 메인 콘텐츠 영역 -->
    <main class="login-box">
      <h2>내 정보</h2> <!-- 섹션 제목 -->

      <!-- 세션에서 가져온 회원 정보를 출력 -->
      <p><strong>회원번호</strong> <?= htmlspecialchars($cno) ?></p>
      <p><strong>이름</strong> <?= htmlspecialchars($name) ?></p>
      <p><strong>비밀번호</strong> <?= htmlspecialchars($passwd) ?></p>
      <p><strong>이메일</strong> <?= htmlspecialchars($email) ?></p>
      <p><strong>여권번호</strong> <?= htmlspecialchars($passport) ?></p>
      <!-- htmlspecialchars: XSS 공격 방지를 위해 특수문자 이스케이프 처리 -->
    </main>

    <!-- 하단 푸터 영역 -->
    <footer>
      <!-- 로그아웃 버튼: 클릭 시 logout.php로 이동하여 세션 종료 -->
      <a href="logout.php" style="float: right; font-size: 14px; color: #555; text-decoration: none;">
        로그아웃
      </a>
    </footer>
  </div>
</body>
</html>
