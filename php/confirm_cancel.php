<?php
session_start();

try {
    $pdo = require __DIR__ . '/db.php';
} catch (PDOException $e) {
    die("❌ DB 연결 실패: " . $e->getMessage());
}

// 세션에서 사용자 번호(cno) 가져오기, POST에서 취소할 예약 정보 받기
$cno = $_SESSION['cno'] ?? '';
$flightNo = $_POST['flightNo'] ?? '';
$departureDateTime = $_POST['departureDateTime'] ?? '';
$seatClass = $_POST['seatClass'] ?? '';

// 요청 데이터 로그로 기록 (디버깅용)
error_log("cno: $cno, flightNo: $flightNo, departureDateTime: $departureDateTime, seatClass: $seatClass");

// 필수 데이터가 하나라도 없으면 400 Bad Request 응답 후 종료
if (!$cno || !$flightNo || !$departureDateTime || !$seatClass) {
    http_response_code(400);
    exit;
}

// 날짜 형식을 Oracle TO_TIMESTAMP에 맞게 'YYYY-MM-DD HH:MM:SS'로 변환
$departureDateTime = date('Y-m-d H:i:s', strtotime($departureDateTime));

try {
    // 트랜잭션 시작: 여러 쿼리를 하나의 작업 단위로 묶음
    $pdo->beginTransaction();

    // Oracle SYSDATE 값을 문자열 형태로 가져와 $now 변수에 저장 (로그 및 날짜 계산용)
    $now = null;
    $stmtNow = $pdo->query("SELECT TO_CHAR(SYSDATE, 'YYYY-MM-DD HH24:MI:SS') AS now FROM DUAL");
    if ($stmtNow) {
        $nowRow = $stmtNow->fetch(PDO::FETCH_ASSOC);
        if ($nowRow && isset($nowRow['now'])) {
            $now = $nowRow['now'];
        }
    }
    error_log("현재 SYSDATE: $now");

    // 예약 정보를 가져오기 위한 쿼리 준비
    // RESERVE와 AIRPLAIN 테이블 JOIN, 
    // 해당 예약이 아직 취소되지 않았고 출발일이 현재 이후인 경우만 조회
    $stmtRefund = $pdo->prepare("
        SELECT r.payment, r.reserveDateTime
        FROM RESERVE r
        JOIN AIRPLAIN a ON r.flightNo = a.flightNo AND r.departureDateTime = a.departureDateTime
        WHERE r.cno = :cno
          AND r.flightNo = :flightNo
          AND r.departureDateTime = TO_TIMESTAMP(:departureDateTime, 'YYYY-MM-DD HH24:MI:SS')
          AND r.seatClass = :seatClass
          AND a.departureDateTime > SYSDATE
          AND NOT EXISTS (
              SELECT 1 FROM CANCEL c
              WHERE c.cno = r.cno
                AND c.flightNo = r.flightNo
                AND c.departureDateTime = r.departureDateTime
                AND c.seatClass = r.seatClass
          )
    ");

    // 쿼리에 바인딩된 값들 전달하여 실행
    $stmtRefund->execute([
        ':cno' => $cno,
        ':flightNo' => $flightNo,
        ':departureDateTime' => $departureDateTime,
        ':seatClass' => $seatClass
    ]);
    // 결과 fetch (결제금액과 예약일자)
    $reserve = $stmtRefund->fetch(PDO::FETCH_ASSOC);

    // 예약 정보가 없으면 롤백 후 404 Not Found 응답 후 종료
    if (!$reserve) {
        $pdo->rollBack();
        http_response_code(404);
        exit;
    }

    error_log("reserve row: " . var_export($reserve, true));

    // 결제금액 가져오기 (문자열이나 NULL 대비)
    $paymentRaw = $reserve['PAYMENT'] ?? null;
    error_log("raw payment: " . var_export($paymentRaw, true));
    // 숫자인지 확인 후 정수로 변환, 아니면 0 처리
    $payment = is_numeric($paymentRaw) ? (int)$paymentRaw : 0;
    error_log("parsed payment: $payment");

    // 출발일까지 남은 일 수 계산 쿼리 준비 (SYSDATE와 비교)
    $stmtDaysDiff = $pdo->prepare("
        SELECT TRUNC(TO_TIMESTAMP(:departureDateTime, 'YYYY-MM-DD HH24:MI:SS')) - TRUNC(SYSDATE) AS daysUntilDeparture FROM DUAL
    ");
    // 쿼리 실행
    $stmtDaysDiff->execute([':departureDateTime' => $departureDateTime]);
    // 결과 fetch
    $diffResult = $stmtDaysDiff->fetch(PDO::FETCH_ASSOC);
    // 결과 컬럼명은 대문자로 전달되므로 주의, 없으면 -1로 기본 설정
    $daysUntilDeparture = isset($diffResult['DAYSUNTILDEPARTURE']) ? (int)$diffResult['DAYSUNTILDEPARTURE'] : -1;

    // 페널티 계산: 출발일까지 남은 일 수 기준으로 금액 차감
    if ($daysUntilDeparture >= 15) {
        $penalty = 150000;    // 15일 이상: 15만원
    } elseif ($daysUntilDeparture >= 4) {
        $penalty = 180000;    // 4~14일: 18만원
    } elseif ($daysUntilDeparture >= 1) {
        $penalty = 250000;    // 1~3일: 25만원
    } else {
        $penalty = $payment;  // 출발일 당일 또는 지난 경우: 전액 패널티 (환불 없음)
    }

    // 환불금 계산 (페널티 제외 후 음수 방지)
    $refundAmount = max(0, $payment - $penalty);

    // 계산된 값들 로그 출력 (디버깅용)
    error_log("cno: $cno");
    error_log("flightNo: $flightNo");
    error_log("departureDateTime: $departureDateTime");
    error_log("seatClass: $seatClass");
    error_log("payment: $payment");
    error_log("daysUntilDeparture: $daysUntilDeparture");
    error_log("penalty: $penalty");
    error_log("refundAmount: $refundAmount");

    // CANCEL 테이블에 취소 기록 추가
    $stmtCancel = $pdo->prepare("
        INSERT INTO CANCEL (flightNo, departureDateTime, seatClass, refund, cancelDateTime, cno)
        VALUES (:flightNo, TO_TIMESTAMP(:departureDateTime, 'YYYY-MM-DD HH24:MI:SS'), :seatClass, :refund, SYSTIMESTAMP, :cno)
    ");
    $stmtCancel->execute([
        ':flightNo' => $flightNo,
        ':departureDateTime' => $departureDateTime,
        ':seatClass' => $seatClass,
        ':refund' => (string)$refundAmount, // 숫자형이지만 문자열로 바인딩해도 무방
        ':cno' => $cno
    ]);

    // RESERVE 테이블에서 해당 예약 삭제
    // departureDateTime은 정확히 일치하지 않을 수 있으므로 ±1초 범위로 잡음 (정밀도 문제 대비)
    $stmtDelete = $pdo->prepare("
        DELETE FROM RESERVE 
        WHERE cno = :cno 
          AND flightNo = :flightNo 
          AND departureDateTime BETWEEN TO_TIMESTAMP(:departureDateTime, 'YYYY-MM-DD HH24:MI:SS') - INTERVAL '1' SECOND
                                 AND TO_TIMESTAMP(:departureDateTime, 'YYYY-MM-DD HH24:MI:SS') + INTERVAL '1' SECOND
          AND seatClass = :seatClass
    ");
    $stmtDelete->execute([
        ':cno' => $cno,
        ':flightNo' => $flightNo,
        ':departureDateTime' => $departureDateTime,
        ':seatClass' => $seatClass
    ]);

    // 예약 삭제가 안 됐으면 롤백 후 404 에러 응답 (이미 취소됐거나 없는 예약)
    if ($stmtDelete->rowCount() === 0) {
        $pdo->rollBack();
        http_response_code(404);
        exit;
    }

    // 모든 작업 정상 완료 시 커밋
    $pdo->commit();
    http_response_code(200);
    exit;

} catch (PDOException $e) {
    // 예외 발생 시 트랜잭션 롤백 및 서버 오류 응답
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("❗ confirm_cancel.php PDOException: " . $e->getMessage());
    http_response_code(500);
    echo "서버 오류 발생: " . htmlspecialchars($e->getMessage());
    exit;
}
?>
