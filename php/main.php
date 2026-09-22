<?php
// 세션을 시작하여 로그인 상태를 확인하고 사용자 정보를 유지할 수 있도록 함
session_start();
?>

<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Vela Sky 메인</title>

  <!-- 메인 페이지용 CSS 파일 불러오기 -->
  <link rel="stylesheet" href="../css/main.css" />
</head>
<body>
  <div class="container">
    <!-- 페이지 상단 로고 영역 -->
    <header>
      <h1 class="logo">Vela Sky</h1>
    </header>

    <main>
      <!-- 메뉴 섹션 시작 -->
      <section class="menu">

        <!-- 회원 관련 메뉴 -->
        <div class="menu-group">
          <h2>회원</h2>
          <div class="menu-items">
            <?php if (isset($_SESSION['cno'])): ?>
              <!-- 로그인된 상태인 경우 '내 정보'로 이동 가능 -->
              <a href="../php/mypage.php">내 정보</a>
            <?php else: ?>
              <!-- 로그인되지 않은 경우 로그인 및 회원가입 페이지로 이동 -->
              <a href="../html/login.html">로그인 및 회원가입</a>
            <?php endif; ?>
          </div>
        </div>

        <!-- 항공권 예약 메뉴 -->
        <div class="menu-group">
          <h2>
            <a href="../php/flight_search.php"><strong>항공권 예약</strong></a>
          </h2>
        </div>

        <!-- 항공권 취소 메뉴 -->
        <div class="menu-group">
          <h2>
            <a href="../php/cancel.php"><strong>항공권 취소</strong></a>
          </h2>
        </div>

        <!-- 항공권 예약 및 취소 내역 조회 메뉴 -->
        <div class="menu-group">
          <h2>
            <a href="../php/history.php"><strong>항공권 예약/취소 내역 조회</strong></a>
          </h2>
        </div>

        <!-- 관리자 메뉴 (관리자일 때만 보임) -->
        <?php if (isset($_SESSION['cno']) && $_SESSION['cno'] === 'c0'): ?>
          <div class="menu-group">
            <h2>관리자</h2>
            <div class="menu-items">
              <!-- 관리자 전용 통계 대시보드 -->
              <a href="../php/admin.php">관리자 전용 통계 대시보드</a>
            </div>
          </div>
        <?php endif; ?>

      </section>
    </main>
  </div>
</body>
</html>
