<!DOCTYPE html>
<html lang="ko"> <!-- 문서의 언어를 한국어로 설정 -->
<head>
  <meta charset="UTF-8"> <!-- 문자 인코딩을 UTF-8로 설정하여 한글이 깨지지 않도록 함 -->
  <title>로그인 및 회원가입</title> <!-- 브라우저 탭에 표시될 제목 -->
  <link rel="stylesheet" href="../css/flight_search.css"> <!-- 외부 CSS 파일 연결 -->
</head>
<body>
  <div class="container"> <!-- 전체 페이지 콘텐츠를 감싸는 최상위 div -->

    <!-- 상단 헤더: 로고 및 페이지 제목 -->
    <header>
      <a href="../php/main.php" class="logo">Vela Sky</a> <!-- 로고 클릭 시 메인 페이지로 이동 -->
      <span class="page-title">항공권 예약</span> <!-- 현재 페이지의 이름 표시 -->
    </header>

    <!-- 본문 영역 시작 -->
    <main class="search-box">
      <h2>항공권 검색</h2> <!-- 메인 제목 -->

      <!-- ✅ 항공권 검색 폼 시작 -->
      <form method="GET" action="../php/flight_search_result.php" onsubmit="return validateForm()">
        <!-- 출발지/도착지 선택 영역 -->
        <div class="airport-selection">
          <div class="airport">
            <label for="departure">출발공항</label> <!-- 출발공항 레이블 -->
            <select id="departure" name="departure"> <!-- 출발공항 선택 드롭다운 -->
              <option value="">선택</option> <!-- 기본값 -->
              <option value="ICN">인천(ICN)</option>
            </select>
          </div>

          <!-- 비행기 아이콘 (디자인용 이미지) -->
          <div class="center-img">
            <img src="../img/flight.png" alt="flight icon"> <!-- 비행기 이미지 표시 -->
          </div>

          <div class="airport">
            <label for="arrival">도착공항</label> <!-- 도착공항 레이블 -->
            <select id="arrival" name="arrival"> <!-- 도착공항 선택 드롭다운 -->
              <option value="">선택</option>
              <option value="GMP">김포(GMP)</option>
            </select>
          </div>
        </div>

        <!-- 출발 날짜 입력 -->
        <div class="form-section">
          <label for="departureDate">출발날짜</label>
          <input type="date" id="departureDate" name="departureDate"> <!-- 달력에서 날짜 선택 -->
        </div>

        <!-- 좌석 등급 선택 -->
        <div class="form-section">
          <label>좌석등급</label>
          <div class="seat-buttons"> <!-- 좌석 등급 선택 버튼 -->
            <button type="button" onclick="selectSeat(this, 'Economy')">Economy</button>
            <button type="button" onclick="selectSeat(this, 'Business')">Business</button>
          </div>
          <input type="hidden" id="seatClass" name="seatClass" value=""> <!-- 실제 서버로 넘길 좌석 등급 정보 -->
        </div>

        <!-- 항공편 검색 버튼 -->
        <div class="submit-button">
          <button type="submit">항공편 검색</button> <!-- 검색 실행 -->
        </div>
      </form>
      <!-- ✅ 항공권 검색 폼 끝 -->

      <!-- ✅ 자바스크립트: 입력값 확인 및 좌석등급 선택 처리 -->
      <script>
        // 폼 제출 시 필수 항목 체크
        function validateForm() {
          const dep = document.getElementById("departure").value;
          const arr = document.getElementById("arrival").value;
          const date = document.getElementById("departureDate").value;
          const seat = document.getElementById("seatClass").value;

          // 항목이 하나라도 비어있으면 경고 후 제출 막기
          if (!dep || !arr || !date || !seat) {
            alert("모든 항목을 입력해주세요.");
            return false;
          }
          return true;
        }

        // 좌석 등급 선택 시 처리 함수
        function selectSeat(button, seat) {
          // 숨겨진 input 태그에 선택된 좌석 등급 값 저장
          document.getElementById('seatClass').value = seat;

          // 모든 버튼의 active 클래스 제거
          document.querySelectorAll('.seat-buttons button').forEach(btn => {
            btn.classList.remove('active');
          });

          // 클릭된 버튼에 active 클래스 추가 (선택 표시)
          button.classList.add('active');
        }
      </script>
    </main>
  </div>
</body>
</html>
