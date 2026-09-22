<?php
session_start(); // 세션 시작

// Oracle DB 연결 정보 설정
$tns = "
(DESCRIPTION=
    (ADDRESS_LIST=(ADDRESS=(PROTOCOL=TCP)(HOST=localhost)(PORT=1521)))
    (CONNECT_DATA=(SERVICE_NAME=XE))
)";
$dsn = "oci:dbname=" . $tns . ";charset=utf8";
$username = 'd202302554';
$password = '1234';

try {
    // DB 연결 시도
    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); // 예외 발생 시 예외 객체 던지도록 설정
} catch (PDOException $e) {
    // 연결 실패 시 종료
    die("❌ DB 연결 실패: " . $e->getMessage());
}

// 로그인한 사용자 정보 가져오기
$cno = $_SESSION['cno'] ?? '';
if (!$cno) die("❌ 로그인 후 이용해주세요."); // 로그인하지 않은 경우 차단

// GET 파라미터 처리: 기간 필터 (기본: 최근 30일), 타입 필터 (전체/예약/취소)
$from = $_GET['from'] ?? date('Y-m-d', strtotime('-30 days')); // 기본 시작일은 30일 전
$to = $_GET['to'] ?? date('Y-m-d'); // 기본 종료일은 오늘
$type = $_GET['type'] ?? 'all'; // 기본은 전체 조회

$queries = [];     // 쿼리문 조각 저장 배열
$params = [];      // 바인딩할 파라미터 값 저장 배열

// 예약 내역 조회 쿼리 추가
if ($type === 'all' || $type === 'reserve') {
    $queries[] = "
        SELECT '예약' AS type, r.flightNo, TO_CHAR(r.departureDateTime, 'YYYY-MM-DD HH24:MI:SS') AS departureDateTime,
               r.seatClass, r.payment AS amount, a.airline, a.departureAirport, a.arrivalAirport,
               TO_CHAR(a.arrivalDateTime, 'YYYY-MM-DD HH24:MI:SS') AS arrivalDateTime,
               TO_CHAR(r.reserveDatetime, 'YYYY-MM-DD HH24:MI:SS') AS actionDate
        FROM RESERVE r
        JOIN AIRPLAIN a ON r.flightNo = a.flightNo AND r.departureDateTime = a.departureDateTime
        WHERE r.cno = :cno1
          AND r.reserveDatetime BETWEEN TO_DATE(:from1, 'YYYY-MM-DD') AND TO_DATE(:to1, 'YYYY-MM-DD') + 1
          AND NOT EXISTS ( -- 이미 취소된 예약은 제외
              SELECT 1 FROM CANCEL c
              WHERE c.cno = r.cno
                AND c.flightNo = r.flightNo
                AND c.departureDateTime = r.departureDateTime
                AND c.seatClass = r.seatClass
          )
    ";
    $params[':cno1'] = $cno;
    $params[':from1'] = $from;
    $params[':to1'] = $to;
}

// 취소 내역 조회 쿼리 추가
if ($type === 'all' || $type === 'cancel') {
    $queries[] = "
        SELECT '취소' AS type, c.flightNo, TO_CHAR(c.departureDateTime, 'YYYY-MM-DD HH24:MI:SS') AS departureDateTime,
               c.seatClass, c.refund AS amount, a.airline, a.departureAirport, a.arrivalAirport,
               TO_CHAR(a.arrivalDateTime, 'YYYY-MM-DD HH24:MI:SS') AS arrivalDateTime,
               TO_CHAR(c.cancelDateTime, 'YYYY-MM-DD HH24:MI:SS') AS actionDate
        FROM CANCEL c
        JOIN AIRPLAIN a ON c.flightNo = a.flightNo AND c.departureDateTime = a.departureDateTime
        WHERE c.cno = :cno2
          AND c.cancelDateTime BETWEEN TO_DATE(:from2, 'YYYY-MM-DD') AND TO_DATE(:to2, 'YYYY-MM-DD') + 1
    ";
    $params[':cno2'] = $cno;
    $params[':from2'] = $from;
    $params[':to2'] = $to;
}

// 예약과 취소 쿼리를 하나로 합치고 정렬
$finalQuery = implode(" UNION ALL ", $queries) . " ORDER BY actionDate DESC";
$stmt = $pdo->prepare($finalQuery);

// 바인딩 수행
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}

$stmt->execute(); // 쿼리 실행
$results = $stmt->fetchAll(PDO::FETCH_ASSOC); // 결과 전체 가져오기
?>

<!-- HTML 시작 -->
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8" />
  <title>항공권 예약/취소 내역</title>
  <link rel="stylesheet" href="../css/flight_search_result.css" />
</head>
<body>
<div class="container">
  <header>
    <a href="main.php" class="logo">Vela Sky</a>
    <span class="page-title">항공권 예약/취소 내역 조회</span>
  </header>

  <main class="search-box">
    <h2>예약/취소 내역</h2>

    <!-- 필터링 폼: 기간 선택 및 타입 선택 -->
    <form method="GET" action="history.php" class="filter-form">
      <label>기간:
        <input type="date" name="from" value="<?= htmlspecialchars($from) ?>">
        ~
        <input type="date" name="to" value="<?= htmlspecialchars($to) ?>">
      </label>
      <label>
        <select name="type">
          <option value="all" <?= $type === 'all' ? 'selected' : '' ?>>전체 내역</option>
          <option value="reserve" <?= $type === 'reserve' ? 'selected' : '' ?>>예약 내역</option>
          <option value="cancel" <?= $type === 'cancel' ? 'selected' : '' ?>>취소 내역</option>
        </select>
      </label>
      <button type="submit">조회</button>
    </form>

    <!-- 조회 결과 출력 영역 -->
    <div class="flights-container">
      <?php if (count($results) > 0): ?>
        <?php foreach ($results as $row): ?>
          <div class="flight">
            <p>구분: <?= $row['TYPE'] ?></p>
            <p>항공사명: <?= htmlspecialchars($row['AIRLINE']) ?></p>
            <p>운항편명: <?= htmlspecialchars($row['FLIGHTNO']) ?></p>
            <p>출발공항: <?= htmlspecialchars($row['DEPARTUREAIRPORT']) ?></p>
            <p>도착공항: <?= htmlspecialchars($row['ARRIVALAIRPORT']) ?></p>
            <p>출발시간: <?= htmlspecialchars($row['DEPARTUREDATETIME']) ?></p>
            <p>도착시간: <?= htmlspecialchars($row['ARRIVALDATETIME']) ?></p>
            <p><?= $row['TYPE'] === '예약' ? '결제 금액' : '환불 금액' ?>: <?= number_format($row['AMOUNT']) ?>원</p>
            <p><?= $row['TYPE'] ?> 일시: <?= htmlspecialchars($row['ACTIONDATE']) ?></p>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <p>조회된 내역이 없습니다.</p>
      <?php endif; ?>
    </div>
  </main>
</div>
</body>
</html>
