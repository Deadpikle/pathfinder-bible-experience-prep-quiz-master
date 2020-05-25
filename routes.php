<?php

    // Format: basic route string (starts with /) => [class name in controllers folder, function name].
    // if you want to specify GET or POST, you can do so as the first param before the class name.
    // if you want the same route URL to have both GET and POST to separate functions, use a
    // multi-dimensional array (MUST specify all 3 params in this case):
    // 'route' => [['GET', 'FooController', 'get_method'],
    //             ['POST', 'BarController', 'post_method']]

    // 'GET' is optional, but 'POST' is required in the route definition for post requests.
    // We only accept GET and POST at the moment.
    // 'GET' and 'POST' do not need to be in all caps.

    // All controllers belong in the 'controllers' folder.
    // Nested controller names can be used (e.g. API/DownloadAPIController).

    // Make sure route URLs with parameters (e.g. {id} and {userID}) use different names for each param,
    // otherwise the parameters will override one another!
    //
    // Route parameters can be numbers or strings -- the router does not differentiate between
    // the two.
    //
    // Also note that routes will be checked from the top down (first to last). The first
    // match will be taken! So, if you have /blog/{id} and /blog/write (in that order) and
    // the url is /blog/write, the router will choose /blog/{id} since it matches "write"
    // with {id}. Just make sure your routes are in order from "most specific" to "least specific"
    // and you should be good. :)
    
    // Some sample routes:
    // $routes = [
    //     '/' => ['HomeController', 'index'],
    //     '/projects' => ['HomeController', 'projects'],
    //     '/blog' => ['BlogController', 'index'],
    //     '/blog/write' => ['GET', 'BlogController', 'write_post'],
    //     '/blog/{id}' => ['BlogController', 'view_post'],
    //     '/blog/{id}/save' => ['POST', 'BlogController', 'save_blog'],
    //     '/blog/{id}/edit' => [['GET', 'BlogController', 'edit_post'],
    //                           ['POST', 'BlogController', 'save_post']],
    //     '/users/{userID}/blog/{id}/edit' => ['BlogController', 'user_edit_post'],
    //     '/api/blog/{id}/posts' => ['API/BlogsAPILoader', 'load_blog_posts']
    // ];

    // Example URLs that the router can currently handle:
    // '/'
    // '/blog'
    // '/blog/2'
    // '/blog/3/write'
    // '/users/2/blog/3/edit'
    // '/blog/2?' (note the ? there)
    // '/blog/2/?comments=true&foo=bar#comments'
    // Of course, the ? and # portions don't belong in your actual route config. :) Just define
    // the "raw" routes.
    // GET parameters end up in the $request->get array, and the route parameters end up in the
    // $request->routeParams array (e.g. {id}, {userID}).

    $routes = [
        '/' => ['HomeController', 'index'],
        '/index' => ['HomeController', 'index'],
        '/login' => [['GET', 'HomeController', 'showLoginScreen'],
                     ['POST', 'HomeController', 'attemptLogin']],
        '/logout' => ['HomeController', 'logout'],
        '/about' => ['HomeController', 'about'],
        '/active-clubs' => ['HomeController', 'activeClubs'],
        '/study-guides' => ['HomeController', 'studyGuides'],
        '/settings' => [['GET', 'HomeController', 'viewSettings'],
                        ['POST', 'HomeController', 'updateSettings']],
        '/questions' => ['User/QuestionController', 'viewQuestions'],
        '/questions/load' => ['POST', 'User/QuestionController', 'loadQuestions'],

        '/questions/add' => [['GET', 'User/QuestionController', 'createNewQuestion'],
                             ['POST', 'User/QuestionController', 'saveNewQuestion']],
        '/questions/{questionID}/edit' => [['GET', 'User/QuestionController', 'editQuestion'],
                                           ['POST', 'User/QuestionController', 'saveQuestionEdits']],
        '/questions/{questionID}/delete' => [['GET', 'User/QuestionController', 'verifyDeleteQuestion'],
                                            ['POST', 'User/QuestionController', 'deleteQuestion']],
        '/quiz/setup' => ['User/QuizController', 'setupQuiz'],
        '/quiz/take' => ['POST', 'User/QuizController', 'takeQuiz'],
        '/quiz/flash-cards/left-right' => ['POST', 'User/QuizController', 'generateLeftRightFlashCards'],
        '/quiz/flash-cards/front-back' => ['POST', 'User/QuizController', 'generateFrontBackFlashCards'],
        '/quiz/answers/remove' => [['GET', 'User/QuizController', 'checkBeforeRemovingAnswers'],
                                   ['POST', 'User/QuizController', 'removeAnswers']],
        '/quiz/answers/save' => ['POST', 'User/QuizController', 'saveQuizAnswers'],
        '/quiz/questions/flag' => ['POST', 'User/QuizController', 'flagQuestion'],

        '/admin' => ['Admin/AdminController', 'index'],
        '/admin/upload-csv' => [['GET', 'Admin/ImportQuestionsController', 'viewImportPage'],
                                ['POST', 'Admin/ImportQuestionsController', 'saveImportedQuestions']],
        '/admin/users' => ['Admin/UserController', 'viewUsers'],
        '/admin/users/create' => [['GET', 'Admin/UserController', 'createUser'],
                            ['POST', 'Admin/UserController', 'saveCreatedUser']],
        '/admin/users/{userID}/edit' => [['GET', 'Admin/UserController', 'editUser'],
                                         ['POST', 'Admin/UserController', 'saveEditedUser']],
        '/admin/users/{userID}/delete' => [['GET', 'Admin/UserController', 'verifyDeleteUser'],
                                           ['POST', 'Admin/UserController', 'deleteUser']],
    ];
