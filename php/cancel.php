<?php
// 세션 시작: 로그인한 사용자 정보 사용을 위해 반드시 필요
session_start();

try {
    $pdo = require __DIR__ . '/db.php';
} catch (PDOException $e) {
    die("❌ DB 연결 실패: " . $e->getMessage());
}

// 세션에서 사용자 고유 번호(cno) 가져오기
$cno = $_SESSION['cno'] ?? '';
// 로그인하지 않은 상태면 이용 불가 메시지 출력 후 종료
if (!$cno) {
    die("❌ 로그인 후 이용해주세요.");
}

try {
    // 예약 중 취소되지 않은, 출발일이 오늘 이후인 예약 정보 조회 쿼리
    // RESERVE 테이블과 AIRPLAIN 테이블을 조인하여 예약 상세 정보 출력
    // NOT EXISTS 절을 사용하여 CANCEL 테이블에 이미 취소 기록이 없는 예약만 조회
    // 날짜와 시간 출력 형식은 TO_CHAR로 'YYYY-MM-DD HH24:MI:SS'로 지정
    $sql = "
        SELECT r.flightNo, TO_CHAR(r.departureDateTime, 'YYYY-MM-DD HH24:MI:SS') AS departureDateTime,
           r.seatClass, r.payment, a.airline, a.departureAirport, a.arrivalAirport,
           TO_CHAR(a.arrivalDateTime, 'YYYY-MM-DD HH24:MI:SS') AS arrivalDateTime
        FROM RESERVE r
        JOIN AIRPLAIN a ON r.flightNo = a.flightNo AND r.departureDateTime = a.departureDateTime
        WHERE r.cno = :cno
          AND r.departureDateTime > SYSDATE
          AND NOT EXISTS (
            SELECT 1 FROM CANCEL c
            WHERE c.cno = r.cno
              AND c.flightNo = r.flightNo
              AND c.departureDateTime = r.departureDateTime
              AND c.seatClass = r.seatClass
          )
        ORDER BY r.departureDateTime DESC
    ";

    // PDO prepare 문 실행
    $stmt = $pdo->prepare($sql);
    // 파라미터 바인딩 (SQL 인젝션 방지)
    $stmt->bindValue(':cno', $cno);
    $stmt->execute();

    // 결과 모두 fetch, 연관 배열 형태로 저장
    $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // 예약 조회 실패 시 오류 메시지 출력 후 종료
    die("❌ 예약 조회 실패: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8" />
  <title>항공권 취소</title>
  <!-- 외부 CSS 연결 -->
  <link rel="stylesheet" href="../css/flight_search_result.css" />
  <style>
    /* 선택된 항공편을 시각적으로 강조하는 스타일 */
    .flight.selected {
      box-shadow: 0 0 12px 3px #005bac;
      border-color: #005bac;
      background-color: #e6f0ff;
    }
    /* 안내문 텍스트 스타일 */
    .notice {
      margin-top: 30px;
      font-size: 14px;
      color: #666;
      line-height: 1.5;
      text-align: center;
    }
  </style>
  <script>
  // 전역 변수: 선택한 항공권 정보를 저장
  let selectedFlightId = null;

  /**
   * 사용자가 항공권 리스트에서 항공편을 클릭할 때 호출
   * @param {string} flightNo - 항공편 번호
   * @param {string} departureDateTime - 출발 일시 (포맷: YYYY-MM-DD HH24:MI:SS)
   * @param {string} seatClass - 좌석 등급
   */
  function selectFlight(flightNo, departureDateTime, seatClass) {
    // 기존에 선택된 항공편 스타일 제거
    document.querySelectorAll('.flight').forEach(f => f.classList.remove('selected'));

    // HTML 요소 ID 생성 (공백, 콜론 등 특수문자 제거)
    const id = `flight-${flightNo}-${departureDateTime.replace(/[: ]/g, '')}-${seatClass}`;
    const selected = document.getElementById(id);

    if (selected) {
      // 클릭한 항공편 요소에 선택 스타일 추가
      selected.classList.add('selected');

      // 선택한 항공편 정보를 전역 변수에 저장
      selectedFlightId = {
        flightNo,
        departureDateTime,
        seatClass
      };
    }
  }

  /**
   * 선택된 항공권에 대해 취소 요청을 서버에 전송
   */
  function cancelReservation() {
    // 항공권이 선택되지 않았으면 경고
    if (!selectedFlightId) {
      alert("⚠️ 취소할 항공권을 선택하세요.");
      return;
    }

    const { flightNo, departureDateTime, seatClass } = selectedFlightId;

    // 필수 정보 누락 시 경고 후 중단
    if (!flightNo || !departureDateTime || !seatClass) {
      alert("❌ 필요한 정보가 누락되었습니다.");
      return;
    }

    // 취소 여부 최종 확인
    if (!confirm("선택한 항공권을 정말 취소하시겠습니까?")) return;

    // POST 요청에 보낼 폼 데이터 생성
    const bodyData = new URLSearchParams({
      flightNo: flightNo,
      departureDateTime: departureDateTime,
      seatClass: seatClass
    });

    // fetch API를 사용해 비동기 POST 요청 전송
    fetch('../php/confirm_cancel.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded'
      },
      body: bodyData
    })
      .then(res => {
        if (res.ok) {
          // 성공 시 사용자에게 알림 후 페이지 리로드
          alert("✅ 예약이 정상적으로 취소되었습니다.");
          location.reload();
        } else {
          // 실패 시 서버가 응답한 메시지 받아서 출력
          res.text().then(msg => {
            alert("❌ 예약 취소 실패: " + msg);
          });
        }
      })
      .catch(err => {
        // 네트워크 또는 서버 오류 발생 시 알림
        alert("❌ 서버 오류 발생: " + err);
      });
  }
  </script>

</head>
<body>
  <div class="container">
    <header>
      <!-- 로고 클릭 시 메인 화면으로 이동 -->
      <a href="../php/main.php" class="logo">Vela Sky</a>
      <span class="page-title">항공권 취소</span>
    </header>

    <main class="search-box">
      <h2>예약 취소 가능한 항공편</h2>

      <?php if (count($reservations) === 0): ?>
        <!-- 예약 내역 없을 때 메시지 -->
        <p>취소 가능한 예약이 없습니다.</p>
      <?php else: ?>

      <!-- 예약 리스트를 보여주고, 선택한 항공권 정보를 폼에 넣어 전송할 준비 -->
      <form id="cancelForm" method="post" action="../php/confirm_cancel.php">
        <!-- 숨겨진 input: 선택된 항공편 정보 저장용 -->
        <input type="hidden" name="flightNo" id="flightNo" />
        <input type="hidden" name="departureDateTime" id="departureDateTime" />
        <input type="hidden" name="seatClass" id="seatClass" />

        <div class="flights-container">
          <?php foreach ($reservations as $r): ?>
            <?php
              // HTML id용 문자열: 공백 및 콜론 제거 (id에 허용되지 않는 문자)
              $depDtId = str_replace([':', ' '], '', $r['DEPARTUREDATETIME']);
            ?>
            <!-- 각각 항공편 정보를 클릭 가능하도록 div로 표시 -->
            <div
              id="flight-<?= htmlspecialchars($r['FLIGHTNO']) ?>-<?= $depDtId ?>-<?= htmlspecialchars($r['SEATCLASS']) ?>"
              class="flight"
              onclick="selectFlight('<?= htmlspecialchars($r['FLIGHTNO']) ?>', '<?= htmlspecialchars($r['DEPARTUREDATETIME']) ?>', '<?= htmlspecialchars($r['SEATCLASS']) ?>')"
              role="button" tabindex="0"
              onkeypress="if(event.key==='Enter'){selectFlight('<?= htmlspecialchars($r['FLIGHTNO']) ?>', '<?= htmlspecialchars($r['DEPARTUREDATETIME']) ?>', '<?= htmlspecialchars($r['SEATCLASS']) ?>');}"
            >
              <!-- 예약 상세 정보 출력 -->
              <p>항공사명: <?= htmlspecialchars($r['AIRLINE']) ?></p>
              <p>운항편명: <?= htmlspecialchars($r['FLIGHTNO']) ?></p>
              <p>출발공항: <?= htmlspecialchars($r['DEPARTUREAIRPORT']) ?></p>
              <p>도착공항: <?= htmlspecialchars($r['ARRIVALAIRPORT']) ?></p>
              <p>출발시간: <?= htmlspecialchars($r['DEPARTUREDATETIME']) ?></p>
              <p>도착시간: <?= htmlspecialchars($r['ARRIVALDATETIME']) ?></p>
              <p>요금: <?= number_format($r['PAYMENT']) ?>원</p>
            </div>
          <?php endforeach; ?>
        </div>

        <div class="button-wrapper" style="margin-top: 20px;">
          <!-- 취소 버튼: 클릭 시 cancelReservation() JS 함수 호출 -->
          <button type="button" onclick="cancelReservation()">선택한 항공권 취소하기</button>
        </div>
      </form>

      <?php endif; ?>

      <!-- 취소 위약금 안내 문구 -->
      <div class="notice">
        ※ 취소 위약금 안내: 취소 위약금은 출발날짜를 기준으로 계산됩니다.<br />
        출발날짜를 기준으로 15일 이전에 취소 시, 위약금은 150,000원이며, <br />
        출발날짜를 기준으로 14일에서 4일 사이에 취소 시, 위약금은 180,000원이며, <br />
        출발날짜를 기준으로 3일 이내에 취소 시, 위약금은 250,000원이며,<br />
        당일 취소는 전액 위약금입니다. <br />
        (※ 실제 환불 기능은 구현되어 있지 않습니다.)
      </div>
    </main>
  </div>
</body>
</html>
