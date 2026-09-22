<?php
session_start(); 
// 세션 시작: 로그인 상태 유지와 사용자 정보 저장을 위해 세션을 시작한다.

try {
    $conn = require_once __DIR__ . '/db.php';

    // HTTP 요청 방식이 POST인지 검사하여 로그인 처리 수행
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // POST로 전달된 회원번호와 비밀번호 값을 받아온다.
        $memberId = $_POST['memberId'] ?? '';
        $inputPassword = $_POST['password'] ?? '';

        // 회원번호나 비밀번호가 비어있으면 경고 메시지 출력 후 스크립트 종료
        if (!$memberId || !$inputPassword) {
            die("⚠️ 회원번호와 비밀번호를 모두 입력해주세요.");
        }

        // 입력한 회원번호에 해당하는 회원 정보를 CUSTOMER 테이블에서 조회하는 SQL 준비
        $sql = "SELECT name, passwd, email, passportNumber FROM CUSTOMER WHERE cno = :cno";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':cno', $memberId); // 바인딩 변수를 통해 SQL 인젝션 방지
        $stmt->execute();

        // 조회 결과를 연관 배열로 가져온다.
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            // Oracle 컬럼명이 대문자이므로 배열 키도 대문자임에 주의
            $dbPasswd = $row['PASSWD'] ?? '';
            // 입력한 비밀번호와 DB에 저장된 비밀번호를 비교한다.
            if ($inputPassword === $dbPasswd) {
                // 비밀번호가 일치하면 세션 변수에 회원정보 저장
                $_SESSION['cno'] = $memberId;
                $_SESSION['name'] = $row['NAME'] ?? '';
                $_SESSION['email'] = $row['EMAIL'] ?? '';

                // 로그인 성공 시 알림창 표시 후 마이페이지로 리다이렉트
                echo "<script>
                        alert('✅ 로그인 성공!');
                        window.location.href = '../php/mypage.php';
                      </script>";
                exit; // 스크립트 종료
            } else {
                // 비밀번호가 틀리면 경고창 띄우고 이전 페이지로 이동
                echo "<script>
                        alert('❌ 비밀번호가 일치하지 않습니다.');
                        history.back();
                      </script>";
                exit;
            }
        } else {
            // 회원번호가 존재하지 않으면 경고창 띄우고 이전 페이지로 이동
            echo "<script>
                    alert('❌ 존재하지 않는 회원번호입니다.');
                    history.back();
                  </script>";
            exit;
        }
    }

} catch (PDOException $e) {
    // DB 연결이나 쿼리 실행 중 예외가 발생하면 오류 메시지 출력
    echo "❌ DB 연결 오류: " . $e->getMessage();
}
?>
