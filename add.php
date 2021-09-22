<?php

require("helpers.php");
require("init.php");

$types = ['quote', 'text', 'photo', 'link', 'video'];
$russianTranslation = [
    'heading' => 'Заголовок',
    'cite-text' => 'Текст цитаты',
    'tags' => 'Теги',
    'post-link' => 'Ссылка',
    'photo-url' => 'Ссылка из интернета',
    'post-text' => 'Текст поста',
    'video-url' => 'Ссылка YOUTUBE',
    'error' => 'Выберите фото'
];

$formType = $_GET['form-type'] ?? "";
$contentType = mysqli_query($connect, "SELECT * FROM content_type");

if (!$contentType) {
    print("Ошибка подготовки запроса: " . mysqli_error($connect));
    exit();
}

$contentTypes = mysqli_fetch_all($contentType, MYSQLI_ASSOC);

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rules = [
        'heading' => isCorrectLength('heading', 10, 35),
        'tags' => getTags('tags'),
    ];

    switch ($formType) {
        case 'quote';
            $rules['cite-text'] = isCorrectLength('cite-text', 10, 70);
            break;

        case 'text':
            $rules['post-text'] = isCorrectLength('post-text', 10, 1000);
            break;

        case 'link':
            $rules['post-link'] = validateUrl($_POST['post-link']);
            break;

        case 'video':
            $rules['video-url'] = check_youtube_url($_POST['video-url']);
            break;

        case 'photo':
            if (!empty($_FILES['userpic-file-photo']['name'])) {
                $rules['userpic-file-photo'] = validateFile('userpic-file-photo');
                $tmpDir = $_FILES['userpic-file-photo']['tmp_name'];
                $filePath = __DIR__.'/uploads/';
                $fileName = $_FILES['userpic-file-photo']['name'];
                move_uploaded_file($tmpDir,$filePath.$fileName);
            } else {
                $rules['photo-url'] = validateUrl($_POST['photo-url']);
            }
            break;
    }

    foreach ($_POST as $key => $value) {
        if (isset($rules[$key]) && is_string($rules[$key])) {
            $rule = $rules[$key];
            $errors[$key] = $rule;
        }
    }

    $errors = array_filter($errors);

    if (empty($errors)) {
        $title = mysqli_real_escape_string($connect, $_POST['heading']);
        $userId = 3;
        $tagsAntiInjection = mysqli_real_escape_string($connect, $_POST['tags']);
        $tagsId = upsertTags($tagsAntiInjection, $connect);

        if (isset($_GET['form-type'])) {
            switch ($formType) {
                case 'quote':
                    $antiInjection = mysqli_real_escape_string($connect, $_POST['cite-text']);
                    $content = " content='$antiInjection'";
                    $typeId = 1;
                    break;

                case 'text':
                    $antiInjection = mysqli_real_escape_string($connect, $_POST['post-text']);
                    $content = " content='$antiInjection'";
                    $typeId = 2;
                    break;

                case 'link':
                    $antiInjection = mysqli_real_escape_string($connect, $_POST['post-link']);
                    $content = " website_link='$antiInjection'";
                    $typeId = 4;
                    break;

                case 'video':
                    $antiInjection = mysqli_real_escape_string($connect, $_POST['video-url']);
                    $content = " video='$antiInjection'";
                    $typeId = 5;
                    break;

                case 'photo':
                    $typeId = 3;

                    if (!empty($_FILES['userpic-file-photo']['name'])) {
                        $photoFile = $_FILES['userpic-file-photo']['name'];
                        $content = " image='uploads/".$photoFile."'";
                    } else {
                        $antiInjection = mysqli_real_escape_string($connect, $_POST['photo-url']);
                         $content = " image='$antiInjection'";
                    }
                    break;
            }

            $query = "INSERT INTO posts SET title='$title',".$content.", type_id=$typeId, author_id=$userId";

            $result = mysqli_query($connect, $query);

            if (!$result) {
                print("Ошибка подготовки запроса: " . mysqli_error($connect));
                exit();
            } else {
                $lastId = mysqli_insert_id($connect);
                foreach ($tagsId as $tagId) {
                    $query = "INSERT INTO posts_hashtags SET post_id=$lastId, hashtag_id=$tagId";
                    mysqli_query($connect, $query);
                }
            }

            header("Location: post.php?post-id=".$lastId);

        }
    }
}

$content = include_template('adding-post.php', [
    'contentTypes' => $contentTypes,
    'formType' => $formType,
    'types' => $types,
    'errors' => $errors,
    'russianTranslation' => $russianTranslation
]);

$pageInformation = [
    'userName' => 'Ivan',
    'title' => 'readme: добавление публикации',
    'is_auth' => rand(0, 1),
    'content' => $content
];

$layout = include_template('layout.php', $pageInformation);

print($layout);
