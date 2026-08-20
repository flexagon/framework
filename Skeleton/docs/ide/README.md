# 에디터 설정

Flexagon 어노테이션의 **doc block 표기**(`@encrypt` 등)를 위한 보조 파일입니다.

**attribute 표기(`#[Encrypt]`)에는 필요 없습니다.** 실제 클래스이므로 에디터가
다른 심볼과 똑같이 자동완성하고, 정의로 이동하고, 이름을 바꿀 때 따라옵니다.

## VS Code

`.vscode/flexagon.code-snippets` 가 프로젝트에 이미 들어 있어 별도 설정이
필요 없습니다. PHP 파일에서 `flx-` 를 입력하면 목록이 나옵니다.

## PhpStorm

라이브 템플릿은 프로젝트가 아니라 IDE 설정 디렉터리에 저장됩니다. 설정
화면에는 가져오기 버튼이 없으므로(`+ / - / 복제 / 기본값 복원` 뿐입니다)
파일을 설정 디렉터리의 `templates/` 안에 직접 넣습니다.

```
macOS    ~/Library/Application Support/JetBrains/PhpStorm<버전>/templates/
Linux    ~/.config/JetBrains/PhpStorm<버전>/templates/
Windows  %APPDATA%\JetBrains\PhpStorm<버전>\templates\
```

```sh
mkdir -p "<위 경로>"
cp docs/ide/phpstorm-live-templates.xml "<위 경로>/Flexagon.xml"
```

파일 이름은 안에 적힌 그룹 이름(`Flexagon`)과 맞춰 둡니다. `templates/`
디렉터리는 사용자 템플릿을 처음 만들 때 생기므로 없으면 직접 만들면 됩니다.
넣은 뒤 PhpStorm 을 재시작하면 Settings → Editor → Live Templates 에
`Flexagon` 그룹이 보입니다.

이제 PHP 파일에서 `flx-encrypt`, `flx-timestamp`, `flx-exclude`, `flx-dao` 를
입력하고 Tab 을 누르면 확장됩니다.

### `@` 로 시작하는 태그에 경고가 뜬다면

PhpStorm 은 모르는 doc block 태그를 "Undefined PHPDoc tag" 로 표시합니다.
자동완성과는 별개이며, 아래에서 끌 수 있습니다.

```
Settings → Editor → Inspections → PHP → PHPDoc → Undefined PHPDoc tag
```

attribute 표기를 쓰면 이 경고 자체가 생기지 않습니다.
