# VelaSky 항공권 예매 시스템

## 실행 환경
- PHP 8.1.3 (64bit, VS16)
- Oracle 18c XE 18.4 또는 Oracle Instant Client 21.3
- Apache 2.4.52 (64bit, VS16)
- Visual C++ Redistributable for Visual Studio 2015 이상

## 설치 및 실행 방법
1. 전체 프로젝트를 웹 서버에 복사합니다.
   예: XAMPP 사용 시 `htdocs/VelaSky` 폴더에 복사

2. Oracle 데이터베이스 생성 및 초기화
   - 제공된 SQL 파일이 있다면, 이를 Oracle DB에 실행하여 테이블과 데이터를 생성합니다.

3. DB 접속 정보 설정
   - `VelaSky/php/config.php` 파일을 열어 자신의 Oracle DB 정보(사용자, 비밀번호, 호스트 등)를 수정합니다.

4. PHP 의존성 설치
   - 명령 프롬프트(cmd) 또는 터미널을 열고, `VelaSky/php` 디렉토리로 이동한 후 아래 명령어 실행:
     composer install

5. 웹 브라우저에서 아래 주소로 접속하여 시스템 실행 확인:
   http://localhost/VelaSky/php/main.php

## 프로젝트 구조 안내
- CSS 파일: `VelaSky/css/`
- HTML 파일: `VelaSky/html/`
- 이미지 파일: `VelaSky/img/`
- PHP 파일: `VelaSky/php/`
- PHP 라이브러리 의존성: `VelaSky/vendor/` 폴더에 포함

※ 참고: `vendor` 폴더는 `composer install` 명령어 실행 시 자동 생성되며, 필요한 PHP 라이브러리가 이 안에 설치됩니다.
