<?php
session_start(); // 세션 시작: 로그인 정보 등 세션 변수 사용 가능하게 함

// Oracle 데이터베이스 접속을 위한 TNS (Transparent Network Substrate) 문자열 구성
$tns = "
(DESCRIPTION=
    (ADDRESS_LIST=
        (ADDRESS=(PROTOCOL=TCP)(HOST=localhost)(PORT=1521))
    )
    (CONNECT_DATA=
        (SERVICE_NAME=XE)
    )
)";
$dsn = "oci:dbname=".$tns.";charset=utf8"; // PDO에서 사용할 DSN(Data Source Name) 문자열 생성
$username = 'd202302554';  // Oracle 사용자명
$password = '1234'; // Oracle 비밀번호

try {
    // PDO를 사용하여 Oracle DB에 연결 시도
    $conn = new PDO($dsn, $username, $password);
} catch (PDOException $e) {
   // 연결 실패 시 오류 메시지 출력 후 종료
    die("DB 연결 실패: " . $e->getMessage());
}

// --- GET 요청으로부터 입력값 받아오기 (초기 기본값 지정 포함) ---
$selectedTab = $_GET['selectedTab'] ?? 'revenue'; // 선택된 탭: 'revenue' 또는 'ranking', 기본값은 revenue
$startDate = $_GET['startDate'] ?? '';  // 검색 시작일 (YYYY-MM-DD 형식)
$endDate = $_GET['endDate'] ?? '';  // 검색 종료일 (YYYY-MM-DD 형식)

$flightNo = $_GET['flightNo'] ?? ''; // 항공편 번호 필터
$sort = $_GET['sort'] ?? 'date';  // 정렬 기준 ('date' 또는 'revenue')

// --- WHERE 절 조립용 배열 및 파라미터 배열 준비 ---
$whereConditions = [];
$params = [];

// --- 드롭다운에서 사용할 항공편 목록 조회 (flightNo만 추출, 중복 제거 및 정렬) ---
$stmtFlights = $conn->prepare("SELECT DISTINCT flightNo FROM AIRPLAIN ORDER BY flightNo");
$stmtFlights->execute();
$flightList = $stmtFlights->fetchAll(PDO::FETCH_COLUMN);


if (!empty($startDate)) {
  // 시작일이 존재할 경우: 출발일이 startDate 이후인 조건 추가
    $whereConditions[] = "A.departureDateTime >= TO_DATE(:startDate, 'YYYY-MM-DD')";
    $params[':startDate'] = $startDate;
}
if (!empty($endDate)) {
  // 종료일이 존재할 경우: 출발일이 endDate 이전인 조건 추가
    $whereConditions[] = "A.departureDateTime <= TO_DATE(:endDate, 'YYYY-MM-DD')";
    $params[':endDate'] = $endDate;
}

if (!empty($flightNo)) {
  // 항공편 번호 필터가 있을 경우: 정확히 일치하는 항공편만 선택
    $whereConditions[] = "A.flightNo = :flightNo";
    $params[':flightNo'] = $flightNo;
}

// 최종 WHERE 절 문자열 조립 (조건이 하나라도 있으면 "WHERE ...", 없으면 빈 문자열)
$where = count($whereConditions) > 0 ? "WHERE " . implode(" AND ", $whereConditions) : "";

$operatorCheck = "EXISTS (SELECT 1 FROM CUSTOMER OP WHERE OP.cno = 'c0')";

if ($selectedTab === 'revenue') {
  // 정렬 기준 선택: 'date'일 경우 날짜 → 좌석등급, 아니면 수익 높은 순으로 정렬
    $orderBy = $sort === 'date' ? "dep_date, seatClass" : "net_revenue DESC";

    // 수익 통계 SQL 쿼리
    $sql = "
    SELECT
        TO_CHAR(A.departureDateTime, 'YYYY-MM-DD') AS dep_date,
        CASE 
            WHEN GROUPING(S.seatClass) = 1 THEN 'ALL SEATS'
            ELSE S.seatClass
        END AS seatClass,
        SUM(NVL(R.payment, 0)) - NVL(SUM(C.refund), 0) AS net_revenue
    FROM AIRPLAIN A
    JOIN SEATS S ON A.flightNo = S.flightNo AND A.departureDateTime = S.departureDateTime
    LEFT JOIN RESERVE R ON S.flightNo = R.flightNo AND S.departureDateTime = R.departureDateTime AND S.seatClass = R.seatClass
    LEFT JOIN CANCEL C ON S.flightNo = C.flightNo AND S.departureDateTime = C.departureDateTime AND S.seatClass = C.seatClass AND R.cno = C.cno
    JOIN CUSTOMER CU ON R.cno = CU.cno
    $where
    AND $operatorCheck
    GROUP BY GROUPING SETS (
        (TO_CHAR(A.departureDateTime, 'YYYY-MM-DD'), S.seatClass),
        (TO_CHAR(A.departureDateTime, 'YYYY-MM-DD'))
    )
    ORDER BY $orderBy
    ";

} else {
      // selectedTab이 'ranking'일 때 실행됨 (월별 수익 순위)
    $orderBy = $sort === 'date' ? "dep_month, revenue_rank" : "net_revenue DESC";

    $sql = "
    SELECT * FROM (
    SELECT
        A.flightNo,
        TO_CHAR(A.departureDateTime, 'YYYY-MM') AS dep_month,
        S.seatClass,
        SUM(NVL(R.payment,0)) - NVL(SUM(C.refund), 0) AS net_revenue,
        RANK() OVER (PARTITION BY TO_CHAR(A.departureDateTime, 'YYYY-MM') ORDER BY SUM(NVL(R.payment,0)) - NVL(SUM(C.refund), 0) DESC) AS revenue_rank,
        RANK() OVER (ORDER BY SUM(NVL(R.payment,0)) - NVL(SUM(C.refund), 0) DESC) AS overall_revenue_rank
    FROM AIRPLAIN A
    JOIN SEATS S ON A.flightNo = S.flightNo AND A.departureDateTime = S.departureDateTime
    LEFT JOIN RESERVE R ON S.flightNo = R.flightNo AND S.departureDateTime = R.departureDateTime AND S.seatClass = R.seatClass
    LEFT JOIN CANCEL C ON S.flightNo = C.flightNo AND S.departureDateTime = C.departureDateTime AND S.seatClass = C.seatClass AND R.cno = C.cno
    JOIN CUSTOMER CU ON R.cno = CU.cno
    $where
    AND $operatorCheck
    GROUP BY
        A.flightNo,
        TO_CHAR(A.departureDateTime, 'YYYY-MM'),
        S.seatClass
)
ORDER BY $orderBy

    ";
}

$stmt = $conn->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

function renderTable($selectedTab, $results) {
    ob_start();
    ?>
    <table>
      <?php if ($selectedTab === 'revenue'): ?>
        <thead>
          <tr><th>출발날짜</th><th>좌석등급</th><th>실제 수익(원)</th></tr>
        </thead>
        <tbody>
          <?php foreach ($results as $row): ?>
            <tr>
              <td><?= htmlspecialchars($row['DEP_DATE']) ?></td>
              <td><?= htmlspecialchars($row['SEATCLASS']) ?></td>
              <td><?= number_format($row['NET_REVENUE']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      <?php else: ?>
        <thead>
          <tr><th>항공편</th><th>출발월</th><th>좌석등급</th><th>실제 수익(원)</th><th>월별순위</th><th>전체순위</th></tr>
        </thead>
        <tbody>
          <?php foreach ($results as $row): ?>
            <tr>
              <td><?= htmlspecialchars($row['FLIGHTNO']) ?></td>
              <td><?= htmlspecialchars($row['DEP_MONTH']) ?></td>
              <td><?= htmlspecialchars($row['SEATCLASS']) ?></td>
              <td><?= number_format($row['NET_REVENUE']) ?></td>
              <td><?= $row['REVENUE_RANK'] ?>위</td>
              <td><?= $row['OVERALL_REVENUE_RANK'] ?>위</td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      <?php endif; ?>
    </table>
    <?php
    return ob_get_clean();
}

if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    echo renderTable($selectedTab, $results);
    exit;
}
?>

<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8" />
  <title>관리자 전용 통계 대시보드</title>
  <link rel="stylesheet" href="../css/admin.css" />
</head>
<body>
  <div class="container">
    <header>
      <a href="../php/main.php" class="logo">Vela Sky</a>
      <span class="page-title">관리자 전용 통계 대시보드</span>
    </header>

    <div class="tabs">
      <button type="button" id="tab-revenue" class="<?= $selectedTab === 'revenue' ? 'active' : '' ?>" onclick="selectTab('revenue')">수익 통계</button>
      <button type="button" id="tab-ranking" class="<?= $selectedTab === 'ranking' ? 'active' : '' ?>" onclick="selectTab('ranking')">월별 순위</button>
    </div>

    <form id="search-form">
      <input type="hidden" name="selectedTab" id="selectedTab" value="<?= htmlspecialchars($selectedTab) ?>" />

      <div class="form-section">
        <!-- 날짜 선택 -->
        <label>
        시작일
        <input type="date" name="startDate" value="<?= htmlspecialchars($_GET['startDate'] ?? '') ?>" />
        </label>
        <label>
        종료일
        <input type="date" name="endDate" value="<?= htmlspecialchars($_GET['endDate'] ?? '') ?>" />
        </label>
        <label>
            항공편
            <select name="flightNo">
                <option value="">전체</option>
                <?php foreach ($flightList as $fn): ?>
                <option value="<?= htmlspecialchars($fn) ?>" <?= $flightNo === $fn ? 'selected' : '' ?>>
                    <?= htmlspecialchars($fn) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>
          정렬 기준
          <select name="sort">
            <option value="revenue" <?= $sort === 'revenue' ? 'selected' : '' ?>>수익순</option>
            <option value="date" <?= $sort === 'date' ? 'selected' : '' ?>>출발일순</option>
          </select>
        </label>
      </div>

      <button type="submit" class="search-button">조회하기</button>
    </form>

    <div id="table-container">
      <?= renderTable($selectedTab, $results) ?>
    </div>
  </div>

<script>
  function selectTab(mode) {
    document.getElementById('selectedTab').value = mode;

    document.getElementById('tab-revenue').classList.remove('active');
    document.getElementById('tab-ranking').classList.remove('active');

    if (mode === 'revenue') {
      document.getElementById('tab-revenue').classList.add('active');
    } else {
      document.getElementById('tab-ranking').classList.add('active');
    }

    fetchData();
  }

  document.getElementById('search-form').addEventListener('submit', function(e) {
    e.preventDefault();
    fetchData();
  });

  function fetchData() {
    const form = document.getElementById('search-form');
    const formData = new FormData(form);
    formData.append('ajax', '1');

    const params = new URLSearchParams(formData);

     fetch('admin.php?' + params.toString())
      .then(response => response.text())
      .then(html => {
        document.getElementById('table-container').innerHTML = html;
      })
      .catch(err => {
        alert('데이터 로드 중 오류가 발생했습니다.');
        console.error(err);
      });
  }
</script>

</body>
</html>
