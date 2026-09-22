<?php
session_start(); // 세션 시작: 로그인 사용자 정보를 계속 유지하기 위해 세션 사용

try {
    // DB 연결 시도
    $conn = require_once __DIR__ . '/db.php';

    // GET 방식으로 전달받은 검색 조건값 추출 (삼항 연산자로 기본값 처리)
    $dep = $_GET['departure'] ?? '';
    $arr = $_GET['arrival'] ?? '';
    $date = $_GET['departureDate'] ?? '';
    $seat = $_GET['seatClass'] ?? '';
    $sort = $_GET['sort'] ?? 'price'; // 기본 정렬은 가격 순

    // 필수 값이 하나라도 비어있으면 오류 메시지 출력 후 종료
    if (!$dep || !$arr || !$date || !$seat) {
        die("⚠️ 모든 검색 조건을 입력해주세요.");
    }

    // 정렬 조건에 따라 SQL의 ORDER BY 절을 안전하게 설정
    $allowedSorts = [
        'price' => ['col' => 's.price', 'dir' => 'ASC'],
        'time' => ['col' => 'a.departureDateTime', 'dir' => 'ASC']
    ];
    if (!array_key_exists($sort, $allowedSorts)) {
        $sort = 'price'; // 잘못된 sort 값이면 기본값으로 fallback
    }

    $orderCol = $allowedSorts[$sort]['col'];
    $orderDir = $allowedSorts[$sort]['dir'];

    // 항공권 검색을 위한 SQL 쿼리 작성
    $sql = "
        SELECT s.flightNo, s.seatClass, s.price,
               s.no_of_seats - NVL(r.count, 0) AS seats_left, -- 예약 수를 뺀 잔여 좌석 수
               a.airline, a.departureAirport, a.arrivalAirport,
               TO_CHAR(a.departureDateTime, 'YYYY-MM-DD HH24:MI:SS') AS departureDateTime,
               TO_CHAR(a.arrivalDateTime, 'YYYY-MM-DD HH24:MI:SS') AS arrivalDateTime
        FROM SEATS s
        JOIN AIRPLAIN a ON s.flightNo = a.flightNo AND s.departureDateTime = a.departureDateTime
        LEFT JOIN (
            SELECT flightNo, departureDateTime, seatClass, COUNT(*) AS count
            FROM RESERVE
            GROUP BY flightNo, departureDateTime, seatClass
        ) r ON s.flightNo = r.flightNo AND s.departureDateTime = r.departureDateTime AND s.seatClass = r.seatClass
        WHERE a.departureAirport = :departure
          AND a.arrivalAirport = :arrival
          AND TO_CHAR(a.departureDateTime, 'YYYY-MM-DD') = :departureDate
          AND s.seatClass = :seatClass
        ORDER BY $orderCol $orderDir
    ";

    // 쿼리 준비 및 바인딩
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':departure', $dep);
    $stmt->bindValue(':arrival', $arr);
    $stmt->bindValue(':departureDate', $date);
    $stmt->bindValue(':seatClass', $seat);
    $stmt->execute();

    // 결과 전체를 배열로 fetch
    $flights = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // DB 오류 발생 시 사용자에게 출력
    echo "❌ DB 오류: " . $e->getMessage();
    exit;
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8" />
  <title>항공권 검색 결과</title>
  <link rel="stylesheet" href="../css/flight_search_result.css" />
</head>
<body>
<div class="container">
  <!-- 상단 로고 및 페이지 제목 -->
  <header>
    <a href="main.php" class="logo">Vela Sky</a>
    <span class="page-title">항공권 예약</span>
  </header>

  <!-- 본문 -->
  <main class="search-box">
    <h2>항공권 검색 결과</h2>

    <!-- 정렬 방식 선택 폼 -->
    <div class="sort-box">
      <form method="GET" action="flight_search_result.php">
        <!-- 이전 검색 조건을 유지하면서 정렬만 변경하기 위해 hidden input으로 전달 -->
        <input type="hidden" name="departure" value="<?= htmlspecialchars($dep) ?>" />
        <input type="hidden" name="arrival" value="<?= htmlspecialchars($arr) ?>" />
        <input type="hidden" name="departureDate" value="<?= htmlspecialchars($date) ?>" />
        <input type="hidden" name="seatClass" value="<?= htmlspecialchars($seat) ?>" />

        <!-- 정렬 옵션 선택 -->
        <label>정렬:
          <select name="sort" onchange="this.form.submit()">
            <option value="price" <?= $sort === 'price' ? 'selected' : '' ?>>요금순</option>
            <option value="time" <?= $sort === 'time' ? 'selected' : '' ?>>출발시간순</option>
          </select>
        </label>
      </form>
    </div>

    <!-- 항공편 선택 및 결제 폼 -->
    <form method="POST" action="confirm_payment.php">
      <!-- 선택한 항공편 정보를 담을 hidden input들 -->
      <input type="hidden" name="flightNo" id="flightNo" />
      <input type="hidden" name="departureDateTime" id="departureDateTime" />
      <input type="hidden" name="seatClass" id="seatClass" />

      <div class="flights-container">
        <?php if (count($flights) > 0): ?>
          <?php foreach ($flights as $flight): ?>
            <!-- 개별 항공편 카드. 클릭 시 selectFlight() 호출 -->
            <div class="flight" onclick="selectFlight('<?= htmlspecialchars($flight['FLIGHTNO']) ?>', 
            '<?= htmlspecialchars($flight['DEPARTUREDATETIME']) ?>', '<?= htmlspecialchars($flight['SEATCLASS']) ?>')">
              <p>항공사명: <?= htmlspecialchars($flight['AIRLINE']) ?></p>
              <p>운항편명: <?= htmlspecialchars($flight['FLIGHTNO']) ?></p>
              <p>출발공항: <?= htmlspecialchars($flight['DEPARTUREAIRPORT']) ?></p>
              <p>도착공항: <?= htmlspecialchars($flight['ARRIVALAIRPORT']) ?></p>
              <p>출발날짜시간: <?= htmlspecialchars($flight['DEPARTUREDATETIME']) ?></p>
              <p>도착날짜시간: <?= htmlspecialchars($flight['ARRIVALDATETIME']) ?></p>
              <p>요금: <?= number_format($flight['PRICE']) ?>원</p>
              <p>남은 좌석 수: <?= htmlspecialchars($flight['SEATS_LEFT']) ?></p>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <p>해당 조건의 항공편이 없습니다.</p>
        <?php endif; ?>
      </div>

      <!-- 선택 항공편이 있을 때만 결제 버튼 표시 -->
      <?php if (count($flights) > 0): ?>
        <div class="button-wrapper">
          <button type="submit">결제하기</button>
        </div>
      <?php endif; ?>
    </form>
  </main>
</div>

<!-- 항공편 선택 시 호출되는 JS 함수 -->
<script>
  function selectFlight(flightNo, departureDateTime, seatClass) {
    // 기존 선택 해제
    const allFlights = document.querySelectorAll('.flight');
    allFlights.forEach(f => f.classList.remove('selected'));

    // 선택 항공편 정보 hidden input에 설정
    document.getElementById('flightNo').value = flightNo;
    document.getElementById('departureDateTime').value = departureDateTime;
    document.getElementById('seatClass').value = seatClass;

    // 해당 항공편 div에 selected 클래스 추가 (시각적 효과용)
    const clickedCard = Array.from(allFlights).find(f =>
      f.innerText.includes(flightNo) &&
      f.innerText.includes(departureDateTime)
    );
    if (clickedCard) {
      clickedCard.classList.add('selected');
    }

    // 선택 알림
    alert('항공편이 선택되었습니다.');
  }
</script>

</body>
</html>
