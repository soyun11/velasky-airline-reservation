<?php
session_start(); // 세션 시작: 로그인 정보와 같은 사용자 상태를 유지하기 위해 필요함

// PHPMailer 라이브러리 로드 및 네임스페이스 선언
require 'vendor/autoload.php'; 
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Dotenv\Dotenv;

// .env 파일에서 환경변수 로드 (SMTP 계정정보 등을 안전하게 관리하기 위함)
$dotenv = Dotenv::createImmutable(__DIR__. '/../');
$dotenv->load();

// 로그인 여부 확인: 세션에 사용자 번호(cno)와 이메일이 없으면 로그인 페이지로 리다이렉트
if (!isset($_SESSION['cno']) || !isset($_SESSION['email'])) {
    // 로그인 후 예약 진행할 수 있도록 예약 정보를 세션에 임시 저장
    $_SESSION['pending_reservation'] = [
        'flightNo' => $_POST['flightNo'] ?? '',
        'departureDateTime' => $_POST['departureDateTime'] ?? '',
        'seatClass' => $_POST['seatClass'] ?? ''
    ];
    // 로그인 유도 후 스크립트 종료
    echo "<script>alert('🔐 먼저 로그인해주세요.'); window.location.href='../html/login.html';</script>";
    exit;
}

try {
    $conn = require_once __DIR__ . '/db.php';

    // POST 데이터와 세션값 받아오기 (입력값 검증 준비)
    $flightNo = $_POST['flightNo'] ?? '';
    $departureDateTime = $_POST['departureDateTime'] ?? '';
    $seatClass = $_POST['seatClass'] ?? '';
    $userId = $_SESSION['cno'];
    $userEmail = $_SESSION['email'];

    // 필수 데이터가 하나라도 없으면 접근 거부
    if (!$flightNo || !$departureDateTime || !$seatClass || !$userId) {
        die("❌ 잘못된 접근입니다.");
    }

    $conn->beginTransaction(); // 트랜잭션 시작 - 예약 과정 중 오류 발생 시 롤백 가능

    // 1) 출발 시간이 이미 지났는지 확인하는 쿼리
    $sqlTimeCheck = "
        SELECT COUNT(*) FROM AIRPLAIN
        WHERE flightNo = :flightNo
        AND departureDateTime = TO_TIMESTAMP(:depTime, 'YYYY-MM-DD HH24:MI:SS')
        AND departureDateTime <= SYSDATE
    ";
    $timeCheckStmt = $conn->prepare($sqlTimeCheck);
    $timeCheckStmt->bindValue(':flightNo', $flightNo);
    $timeCheckStmt->bindValue(':depTime', $departureDateTime);
    $timeCheckStmt->execute();
    $pastFlight = $timeCheckStmt->fetchColumn();

    // 출발시간이 지났으면 예약 불가 안내 후 종료
    if ($pastFlight > 0) {
        echo "<script>alert('❌ 이미 출발시간이 지났습니다.'); history.back();</script>";
        exit;
    }

    // 2) 좌석 잔여 여부 확인 쿼리 (전체 좌석 수 - 예약된 좌석 수)
    $sqlCheck = "
        SELECT s.no_of_seats - NVL(r.count, 0) AS seats_left,
               s.price,
               a.airline, a.departureAirport, a.arrivalAirport,
               TO_CHAR(a.departureDateTime, 'YYYY-MM-DD HH24:MI:SS') AS departureDateTime,
               TO_CHAR(a.arrivalDateTime, 'YYYY-MM-DD HH24:MI:SS') AS arrivalDateTime
        FROM SEATS s
        JOIN AIRPLAIN a ON s.flightNo = a.flightNo AND s.departureDateTime = a.departureDateTime
        LEFT JOIN (
            SELECT flightNo, departureDateTime, seatClass, COUNT(*) AS count
            FROM RESERVE
            WHERE flightNo = :flightNo AND departureDateTime = TO_TIMESTAMP(:depTime, 'YYYY-MM-DD HH24:MI:SS') AND seatClass = :seatClass
            GROUP BY flightNo, departureDateTime, seatClass
        ) r ON s.flightNo = r.flightNo AND s.departureDateTime = r.departureDateTime AND s.seatClass = r.seatClass
        WHERE s.flightNo = :flightNo AND s.departureDateTime = TO_TIMESTAMP(:depTime, 'YYYY-MM-DD HH24:MI:SS') AND s.seatClass = :seatClass
    ";
    $stmtCheck = $conn->prepare($sqlCheck);
    $stmtCheck->bindValue(':flightNo', $flightNo);
    $stmtCheck->bindValue(':depTime', $departureDateTime);
    $stmtCheck->bindValue(':seatClass', $seatClass);
    $stmtCheck->execute();
    $flight = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    // 좌석이 없으면 롤백 후 알림 및 종료
    if (!$flight || (int)$flight['SEATS_LEFT'] <= 0) {
        $conn->rollBack();
        echo "<script>alert('❌ 남은 좌석이 없습니다.'); history.back();</script>";
        exit;
    }

    // 3) 중복 예약 체크: 동일 사용자, 동일 항공편에 이미 예약이 있는지 확인
    $dupCheckSql = "
        SELECT COUNT(*) AS cnt FROM RESERVE
        WHERE flightNo = :flightNo AND departureDateTime = TO_TIMESTAMP(:depTime, 'YYYY-MM-DD HH24:MI:SS')
          AND seatClass = :seatClass AND cno = :cno
    ";
    $dupCheckStmt = $conn->prepare($dupCheckSql);
    $dupCheckStmt->bindValue(':flightNo', $flightNo);
    $dupCheckStmt->bindValue(':depTime', $departureDateTime);
    $dupCheckStmt->bindValue(':seatClass', $seatClass);
    $dupCheckStmt->bindValue(':cno', $userId);
    $dupCheckStmt->execute();
    $dupCount = $dupCheckStmt->fetchColumn();

    // 이미 예약된 내역이 있으면 롤백 및 경고 후 종료
    if ($dupCount > 0) {
        $conn->rollBack();
        echo "<script>alert('❌ 이미 동일한 항공편에 대해 예약이 존재합니다.'); history.back();</script>";
        exit;
    }

    // 4) 예약 정보 INSERT 쿼리 실행
    $insertSql = "
        INSERT INTO RESERVE (CNO, flightNo, departureDateTime, seatClass, reserveDateTime, payment)
        VALUES (:cno, :flightNo, TO_TIMESTAMP(:depTime, 'YYYY-MM-DD HH24:MI:SS'), :seatClass, SYSDATE, :payment)
    ";
    $insertStmt = $conn->prepare($insertSql);
    $insertStmt->bindValue(':cno', $userId);
    $insertStmt->bindValue(':flightNo', $flightNo);
    $insertStmt->bindValue(':depTime', $departureDateTime);
    $insertStmt->bindValue(':seatClass', $seatClass);
    $insertStmt->bindValue(':payment', $flight['PRICE']);
    $insertStmt->execute();

    $conn->commit(); // 트랜잭션 커밋: 예약 완료

    // 이메일에 포함할 탑승권 정보 문자열 생성
    $ticketInfo = "탑승권 정보\n"
                . "항공사: {$flight['AIRLINE']}\n"
                . "편명: {$flightNo}\n"
                . "출발지: {$flight['DEPARTUREAIRPORT']}\n"
                . "도착지: {$flight['ARRIVALAIRPORT']}\n"
                . "출발일시: {$flight['DEPARTUREDATETIME']}\n"
                . "도착일시: {$flight['ARRIVALDATETIME']}\n"
                . "좌석 등급: {$seatClass}";

    // PHPMailer를 이용해 이메일 발송 시도
    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = 'smtp.naver.com';           // SMTP 서버 주소
        $mail->SMTPAuth = true;                    // SMTP 인증 활성화
        $mail->Username = $_ENV['SMTP_USER'];     // SMTP 아이디 (환경변수에서 로드)
        $mail->Password = $_ENV['SMTP_PASS'];     // SMTP 비밀번호 (환경변수에서 로드)
        $mail->SMTPSecure = 'ssl';                 // SSL 보안 연결 사용
        $mail->Port = 465;                         // SMTP 포트 번호

        $mail->CharSet = 'UTF-8';                  // 한글 깨짐 방지 문자셋
        $mail->setFrom('567654@naver.com', 'Vela Sky 예약 시스템'); // 발신자 정보
        $mail->addAddress($userEmail, '고객님');  // 수신자 정보 (예약한 사용자)

        $mail->isHTML(true);                       // HTML 형식 메일 설정
        $mail->Subject = '✈️ Vela Sky 예약이 완료되었습니다!'; // 메일 제목
        $mail->Body = nl2br($ticketInfo);         // 본문 (줄바꿈 포함 HTML 변환)
        $mail->AltBody = $ticketInfo;              // HTML 지원 안할 경우 대체 텍스트

        $mail->send(); // 메일 전송

        // 예약 및 이메일 전송 성공 알림 및 메인 페이지로 이동
        echo "<script>
            alert('✅ 예약 및 이메일 전송이 완료되었습니다!');
            window.location.href = 'main.php';
        </script>";
    } catch (Exception $e) {
        // 이메일 발송 실패 시 예약 완료 메시지 출력 및 오류 내용 알림 후 메인 페이지로 이동
        echo "<script>
            alert('예약은 완료되었지만 이메일 전송에 실패했습니다.\\n사유: " . $mail->ErrorInfo . "');
            window.location.href = 'main.php';
        </script>";
    }

} catch (PDOException $e) {
    // DB 처리 중 예외 발생 시 트랜잭션 롤백 및 에러 메시지 출력
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo "❌ DB 오류: " . $e->getMessage();
}
?>
