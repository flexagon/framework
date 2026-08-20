<?php
/**
 * Example landing page.
 *
 * Every file under public/ maps straight to a URL:
 *   public/index.php        ->  /
 *   public/user/profile.php ->  /user/profile
 *   public/user/index.php   ->  /user/
 *
 * Replace this file with your own entry page.
 */

_Global::$SITE_TITLE = 'Flexagon';

TemplateLoader::show('head', ['title' => _Global::$SITE_TITLE]);
?>
<h1>Flexagon</h1>
<p>
    이 페이지는 <code>public/index.php</code> 입니다.
    요청 URL과 <code>public/</code> 아래의 파일 경로가 1:1로 대응합니다.
</p>
<p>
    데이터베이스는 <code>application/_Config.php</code> 에서 설정하고,
    모델과 DAO 작성법은 <code>README.md</code> 를 참고하세요.
    예제 코드는 <code>application/ExampleUser/</code> 에 있습니다.
</p>
<?php
TemplateLoader::show('tail');
