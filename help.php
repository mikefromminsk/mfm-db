<?php

include_once "db.php";

$params = $GLOBALS["params"];

header("Content-type: text/html;charset=utf-8");
$script_name = basename($_SERVER['SCRIPT_NAME']);
$script_title = get_string("script_title");
$files = array_diff(scandir("."), array('.', '..'));

$types = array(
    "string" => "строка",
    "int" => "число",
    "int_array" => "массив чисел",
);

$get_link = ((isset($_SERVER['HTTPS'])) ? "https" : "http") . "://" . ($GLOBALS["host_name"] ?: $_SERVER['SERVER_NAME']) . strtok($_SERVER["REQUEST_URI"], '?') . "?";
foreach ($params as $param)
    $get_link .= $param["name"] . "=" . ($param["default"] ?: ($param["type"] == "int" ? "123" : "abc")) . "&";
$get_link = rtrim($get_link, '&');

$json_params = "";
foreach ($params as $param)
    $json_params .= $param["name"] . ": " . ($param["default"] ?: ($param["type"] == "int" ? "123" : "'abc'")) . ", ";
$json_params = rtrim($json_params, ', ');

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title><?= $script_name ?> | Разработчикам</title>
    <meta charset="UTF-8">
    <meta name="description" content="<?= $script_title ?>">
    <link rel="stylesheet" href="/flex/flex.css">
    <link rel="stylesheet" href="/db/style.css">
</head>

<body class="col align-center">
<div class="row align-center header">
    <div class="row main-container">

        <div class="col logo left-container">
            <h1 class="flex col align-center"><?= $script_name ?></h1>
        </div>

        <div class="col menu active">
            <a href="#" class="flex col align-center">Документация</a>
        </div>

        <div class="col menu">
            <a href="#" class="flex col align-center">Поддержка</a>
        </div>

        <div class="col menu">
            <a href="#" class="flex col align-center">Скачать</a>
        </div>
    </div>
</div>
<div class="col fill align-center">
    <div class="row align-center main_container">
        <div class="col left-container ">
            <div class="left-block">
                <div class="left-block-title">Файлы</div>
                <div class="col-divider"></div>
                <?php foreach ($files as $file): ?>
                    <div class="row left-menu <?= $file == $script_name ? "active" : "" ?>">
                        <a class="row" href="<?= $file ?>?help">
                            <?php if (is_dir($file)): ?>
                                <div class="dir-icon"></div>
                            <?php endif; ?>
                            <?= $file ?>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="col center-container">
            <div class="col center-block">
                <div class="center-block-title">Метод <?= $script_name ?></div>
                <div class="col-divider"></div>
                <div class="center-block-description"><?= $script_title ?></div>
            </div>
            <div class="col center-block">
                <div class="center-block-title">Параметры</div>
                <div class="col">
                    <?php foreach ($params as $param): ?>
                        <div class="col-divider"></div>
                        <div class="row">
                            <div class="param"><?= $param["name"] ?></div>
                            <div class="col param-description">
                                <div><?= $param["description"] ?></div>
                                <div class="param-type">
                                    <?= $types[$param["type"]] ?>,
                                    <b><?= $param["required"] ? "обязательный параметр" : "необязательный параметр" ?></b>
                                    <?php if ($param["default"] !== null): ?>
                                        <div>по умолчанию: <?= $param["default"] ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="col center-block">
                <div class="center-block-title">Пример</div>
                <div class="row example">
                    <form id="form" name="test" class="col example-params">
                        <?php foreach ($params as $param): ?>
                            <label class="example-param-title" ><?= $param["name"] ?></label>
                            <input type="<?= $param["type"] == "number" ? "number" : "text" ?>"
                                   name="<?= $param["name"] ?>" value="<?= $param["default"] ?>"
                                   class="example-param-input" autocomplete="off"
                                   <?= $param["required"] ? "required" : "" ?>>
                        <?php endforeach; ?>
                        <button type="submit" class="example-button">Выполнить</button>
                    </form>
                    <iframe id="frame" frameborder="0" style="width: 100%; height: 100%"></iframe>
                </div>
            </div>
            <div class="col center-block">
                <div class="center-block-title">Способы передачи параметров</div>
                <div class="col-divider"></div>
                <div class="row">
                    <div class="param">GET</div>
                    <div class="flex col param-description">
                        Параметры передаются в стоке запроса в формате URL.
                        <div class="info">
                            <a href="<?= $get_link ?>"><?= $get_link ?></a>
                        </div>
                    </div>
                </div>
                <div class="col-divider"></div>
                <div class="row">
                    <div class="param">form POST</div>
                    <div class="flex col param-description">
                        Параметры передаются с помощью html форм
                        <div id="form_post_request_info" class="info"></div>
                        <script>
                            let formCode = document.getElementById("form").outerHTML
                            formCode = formCode.replaceAll(/class="[a-zA-Z0-9:;\.\s\(\)\-\,]*"/g, "")
                            document.getElementById("form_post_request_info").innerText = formCode
                        </script>
                    </div>
                </div>
                <div class="col-divider"></div>
                <div class="row">
                    <div class="param">json POST</div>
                    <div class="flex col param-description">
                        Параметры передаются с помощью HTTP форм
                        <div id="json_post_request_info" class="info"></div>
                        <script id="json_post_script">
                            function post(url, params, success, error){
                                var xhr = new XMLHttpRequest();
                                xhr.open("POST", url, true);
                                xhr.setRequestHeader("Content-Type", "application/json");
                                xhr.onreadystatechange = function () {
                                    if (xhr.readyState === XMLHttpRequest.DONE) {
                                        let response = JSON.parse(xhr.responseText);
                                        if (xhr.status === 200) {
                                            success(response)
                                        } else {
                                            error(response)
                                        }
                                    }
                                }
                                xhr.send(JSON.stringify(params));
                            }

                            post("<?=$script_name?>", {<?=$json_params?>}, function (data){
                                console.log(data)
                            }, function (error) {
                                console.log(error)
                            })

                        </script>
                        <script>
                            document.getElementById("json_post_request_info").innerText = document.getElementById("json_post_script").innerHTML
                        </script>
                    </div>
                </div>
            </div>


            <div class="col center-block">
                <div class="center-block-title">Передача файлов</div>
                <div class="col-divider"></div>
                <div class="row">
                    <div class="param">form POST</div>
                    <div class="flex col param-description">
                        Фа
                        <div id="json_post_request_info" class="info"></div>
                        <script id="json_post_script">
                            function post(url, params, success, error){
                                var input = document.createElement("input")
                                input.type = "file"
                                input.onchange = function () {
                                    var formData = new FormData()
                                    /*formData.append("password", $scope.password)
                                    formData.append("domain_name", $scope.domain_name)*/
                                    formData.append("file", input.files[0], input.files[0].name)
                                    var xhr = new XMLHttpRequest()
                                    xhr.open("POST", "upload.php", true)
                                    xhr.onreadystatechange = function () {
                                        if (xhr.readyState === XMLHttpRequest.DONE) {
                                            let response = JSON.parse(xhr.responseText);
                                            if (xhr.status === 200) {
                                                success(response)
                                            } else {
                                                error(response)
                                            }
                                        }
                                    }
                                    xhr.send(formData)
                                }
                                input.click()
                            }

                            post("<?=$script_name?>", {<?=$json_params?>}, function (data){
                                console.log(data)
                            }, function (error) {
                                console.log(error)
                            })

                        </script>
                        <script>
                            document.getElementById("json_post_request_info").innerText = document.getElementById("json_post_script").innerHTML
                        </script>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
<script>
    document.getElementById("form").onsubmit =
        function (e) {
            e.preventDefault()
            var kvpairs = [];
            var form = this
            for (var i = 0; i < form.elements.length; i++) {
                var e = form.elements[i];
                kvpairs.push(encodeURIComponent(e.name) + "=" + encodeURIComponent(e.value));
            }
            document.getElementById("frame").setAttribute("src", "?" + kvpairs.join("&"))
        }
</script>


<!--The root path for this version of your API

Authentication and other headers required with each request

The path to call each endpoint

Which HTTP methods can be used with each endpoint

The request data fields and where each goes, such as path, query-string, or body

Explanation of what request data is required and what is optional

Which HTTP status codes are possible for each endpoint/method pairing

What each status code means in the context of each call

The data to expect in each response, including which responses will always be present

Getting started guides and other tutorials

Code repositories and sample applications-->

</body>
</html>