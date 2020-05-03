<?php

use App\Models\BlankableWord;
use App\Models\Question;
use App\Models\Util;

    function str_contains($needle, $haystack) {
        return strpos($haystack, $needle) !== false;
    }

    function utf8($str) {
        return iconv("UTF-8", "ISO-8859-1", $str);
    }

    function get_settings($pdo) {
        // load settings
        $query = '
            SELECT SettingID, SettingKey, SettingValue, DisplayName
            FROM Settings
            ORDER BY DisplayName';
        $settingsStmt = $pdo->prepare($query);
        $settingsStmt->execute([]);
        $settings = $settingsStmt->fetchAll();
        $output = [];
        foreach ($settings as $setting) {
            $output[$setting['SettingKey']] = $setting['SettingValue'];
        }
        return $output;
    }

    function get_active_year($pdo) {
        $query = '
            SELECT YearID, Year
            FROM Years
            WHERE IsCurrent = 1';
        $yearsStmt = $pdo->prepare($query);
        $yearsStmt->execute([]);
        $years = $yearsStmt->fetchAll();
        if (count($years) > 0) {
            return ["YearID" => $years[0]["YearID"], "Year" => $years[0]["Year"]];
        }
        return ["YearID" => 1, "Year" => 2018];
    }

    function get_languages($pdo) {
        $query = '
            SELECT LanguageID, Name, IsDefault, AltName
            FROM Languages
            ORDER BY Name';
        $stmt = $pdo->prepare($query);
        $stmt->execute([]);
        $data = $stmt->fetchAll();
        $languages = [];
        foreach ($data as $row) {
            $languages[] = ["LanguageID" => $row["LanguageID"], "Name" => $row["Name"], "IsDefault" => $row["IsDefault"],
                "AltName" => $row["AltName"]];
        }
        return $languages;
    }

    function get_default_language($pdo) {
        $query = '
            SELECT LanguageID, Name, AltName
            FROM Languages
            WHERE IsDefault = 1';
        $stmt = $pdo->prepare($query);
        $stmt->execute([]);
        $data = $stmt->fetchAll();
        if (count($data) > 0) {
            return ["LanguageID" => $data[0]["LanguageID"], "Name" => $data[0]["Name"], "IsDefault" => 1, "AltName" => $data[0]["AltName"]];
        }
        return ["LanguageID" => 1, "Name" => "English", "AltName" => ""];
    }

    function get_user_language($pdo) {
        $query = '
            SELECT LanguageID, Name, AltName
            FROM Languages
            WHERE LanguageID = ?';
        $stmt = $pdo->prepare($query);
        $stmt->execute([$_SESSION["PreferredLanguageID"]]);
        $data = $stmt->fetchAll();
        if (count($data) > 0) {
            return ["LanguageID" => $data[0]["LanguageID"], "Name" => $data[0]["Name"], 
                "IsDefault" => 1, "AltName" => $data[0]["AltName"]];
        }
        return ["LanguageID" => 1, "Name" => "English", "AltName" => ""];
    }

    function language_display_name($language) {
        $output = $language["Name"];
        if ($language["AltName"] != "") {
            $output .= " (" . $language["AltName"] . ")";
        }
        return $output;
    }

    function load_commentaries($pdo, $onlyLoadCommentariesWithActiveQuestions = false) {
        $currentYear = get_active_year($pdo)["YearID"];
        if ($onlyLoadCommentariesWithActiveQuestions) {
            $query = '
                SELECT DISTINCT c.CommentaryID, Number, TopicName
                FROM Commentaries c JOIN Questions q ON c.CommentaryID = q.CommentaryID
                WHERE YearID = ? AND q.IsDeleted = 0
                ORDER BY Number';
        }
        else {
            $query = '
                SELECT DISTINCT CommentaryID, Number, TopicName
                FROM Commentaries 
                WHERE YearID = ?
                ORDER BY Number';
        }
        $params = [ $currentYear ];
        $commentaryStmt = $pdo->prepare($query);
        $commentaryStmt->execute($params);
        $commentaries = $commentaryStmt->fetchAll();

        $commentariesOutput = array();
        foreach ($commentaries as $commentary) {
            $commentariesOutput[] = [
                'id' => $commentary["CommentaryID"], 
                'name' => "SDA Commentary Volume " . $commentary["Number"],
                'topic' => $commentary["TopicName"]
            ];
        }
        return $commentariesOutput;
    }

    function load_home_sections($pdo, $conferenceID = -1) {
        $currentYear = get_active_year($pdo)["YearID"];
        $params = [
            $currentYear
        ];
        $whereClause = "WHERE his.YearID = ?";
        if ($conferenceID != -1) {
            $whereClause .= " AND his.ConferenceID = ?";
            $params[] = $conferenceID;
        }
        $query = '
            SELECT his.HomeInfoSectionID AS SectionID, his.Name AS SectionName, 
                his.Subtitle AS SectionSubtitle,
                his.SortOrder AS SectionSortOrder,
                hil.HomeInfoLineID AS LineID,
                hii.HomeInfoItemID, hii.Text, hii.IsLink, hii.URL, hii.SortOrder AS ItemSortOrder
            FROM HomeInfoSections his 
                LEFT JOIN HomeInfoLines hil ON his.HomeInfoSectionID = hil.HomeInfoSectionID
                LEFT JOIN HomeInfoItems hii ON hil.HomeInfoLineID = hii.HomeInfoLineID
            ' . $whereClause . '
            ORDER BY SectionSortOrder, hil.SortOrder, ItemSortOrder';
        $sectionStmt = $pdo->prepare($query);
        $sectionStmt->execute($params);
        $sections = $sectionStmt->fetchAll();
        return $sections;
    }

    function get_total_number_of_bible_fill_questions_for_current_year($pdo) {
        $currentYear = get_active_year($pdo)["YearID"];
        $query = '
            SELECT COUNT(q.QuestionID) AS QuestionCount
            FROM Questions q JOIN Verses v ON q.StartVerseID = v.VerseID 
                JOIN Chapters c ON c.ChapterID = v.ChapterID
                JOIN Books b ON b.BookID = c.BookID
            WHERE b.YearID = ? 
                AND q.Type = "bible-qna-fill"';
        $stmt = $pdo->prepare($query);
        $stmt->execute([$currentYear]);
        $bookQuestionData = $stmt->fetch();
        if ($bookQuestionData != null) {
            return $bookQuestionData['QuestionCount'];
        }
        return 0;
    }

    function get_total_number_of_bible_fill_questions_by_language_for_current_year($pdo) {
        $currentYear = get_active_year($pdo)["YearID"];
        $languages = get_languages($pdo);
        $fillIns = [];
        $query = '
            SELECT COUNT(q.QuestionID) AS QuestionCount
            FROM Questions q JOIN Verses v ON q.StartVerseID = v.VerseID 
                JOIN Chapters c ON c.ChapterID = v.ChapterID
                JOIN Books b ON b.BookID = c.BookID
            WHERE b.YearID = ? 
                AND q.LanguageID = ?
                AND q.Type = "bible-qna-fill"';
        $stmt = $pdo->prepare($query);
        foreach ($languages as $language) {
            $stmt->execute([
                $currentYear,
                $language["LanguageID"]
            ]);
            $bookQuestionData = $stmt->fetch();
            if ($bookQuestionData != null) {
                $fillIns[$language["LanguageID"]] = $bookQuestionData["QuestionCount"];
            }
            else {
                $fillIns[$language["LanguageID"]] = 0;
            }
        }
        return $fillIns;
    }

    function get_web_admin_conference_id($pdo) {
        $query = 'SELECT ConferenceID FROM Conferences WHERE Name = "Website Administrators"';
        $conferenceStmnt = $pdo->prepare($query);
        $conferenceStmnt->execute([]);
        $conferences = $conferenceStmnt->fetchAll();
        if (count($conferences) > 0) {
            return $conferences[0]["ConferenceID"];
        }
        return -1;
    }

    function copy_home_sections($pdo, $fromConferenceID, $toConferenceID, $fromYearID) {
        $toYearID = get_active_year($pdo)["YearID"];
        // load all sections from other conference and year
        $sectionQuery = '
            SELECT his.HomeInfoSectionID AS SectionID, his.Name AS SectionName, his.Subtitle AS SectionSubtitle
            FROM HomeInfoSections his 
            WHERE ConferenceID = ? AND YearID = ?
            ORDER BY SortOrder';
        $sectionParams = [
            $fromConferenceID, 
            $fromYearID
        ];
        $sectionStmt = $pdo->prepare($sectionQuery);
        $sectionStmt->execute($sectionParams);
        // prepare other queries so things go fast
        // need to check for a pre-existing section with that name
        $sectionNameQuery = 'SELECT HomeInfoSectionID FROM HomeInfoSections WHERE Name = ? AND ConferenceID = ? AND YearID = ?';
        $sectionNameStmnt = $pdo->prepare($sectionNameQuery);

        $sectionMaxSortOrderQuery = '
            SELECT MAX(SortOrder) AS MaxSortOrder 
            FROM HomeInfoSections 
            WHERE ConferenceID = ? AND YearID = ?';
        $sectionMaxParams = [
            $toConferenceID,
            $toYearID
        ];
        $sectionMaxSortOrderStmnt = $pdo->prepare($sectionMaxSortOrderQuery);
        $sectionMaxSortOrderStmnt->execute($sectionMaxParams);
        $nextSectionSortOrder = 0;
        $maxSorts = $sectionMaxSortOrderStmnt->fetchAll();
        if (count($maxSorts) > 0) {
            $nextSectionSortOrder = ((int)$maxSorts[0]["MaxSortOrder"]) + 1;
        }
        //die("order = " .$nextSectionSortOrder);

        $insertSection = 'INSERT INTO HomeInfoSections (Name, Subtitle, SortOrder, YearID, ConferenceID) VALUES (?, ?, ?, ?, ?)';
        $insertSectionStmnt = $pdo->prepare($insertSection);
        // --
        $lineMaxSortOrderQuery = '
            SELECT MAX(SortOrder) AS MaxSortOrder 
            FROM HomeInfoLines
            WHERE HomeInfoSectionID = ?';
        $lineMaxSortOrderStmnt = $pdo->prepare($lineMaxSortOrderQuery);

        $lineQuery = '
            SELECT HomeInfoLineID, Name
            FROM HomeInfoLines
            WHERE HomeInfoSectionID = ?
            ORDER BY SortOrder
        ';
        $lineQueryStmnt = $pdo->prepare($lineQuery);

        $insertLine = 'INSERT INTO HomeInfoLines (Name, SortOrder, HomeInfoSectionID) VALUES (?, ?, ?)';
        $insertLineStmnt = $pdo->prepare($insertLine);
        // --
        $itemQuery = '
            SELECT IsLink, Text, URL, SortOrder
            FROM HomeInfoItems
            WHERE HomeInfoLineID = ?
            ORDER BY SortOrder
        ';
        $itemQueryStmnt = $pdo->prepare($itemQuery);
        $insertItem = 'INSERT INTO HomeInfoItems (IsLink, Text, URL, SortOrder, HomeInfoLineID) VALUES (?, ?, ?, ?, ?)';
        $insertItemStmnt = $pdo->prepare($insertItem);
        // start looping through the sections
        foreach ($sectionStmt as $section) {
            // check to see if a section with this name already exists
            $sectionNameCheckParams = [
                $section["SectionName"],
                $toConferenceID, 
                $toYearID
            ];
            $sectionNameStmnt->execute($sectionNameCheckParams);
            $sectionsWithThatName = $sectionNameStmnt->fetchAll();
            if (count($sectionsWithThatName) > 0) {
                $createdSectionID = $sectionsWithThatName[0]["HomeInfoSectionID"];
            }
            else {
                // insert it into the HomeInfoSections table for the given year and conference
                $insertSectionParams = [
                    $section["SectionName"],
                    $section["SectionSubtitle"] ? $section["SectionSubtitle"] : "",
                    $nextSectionSortOrder++,
                    $toYearID,
                    $toConferenceID
                ];
                $insertSectionStmnt->execute($insertSectionParams);
                $createdSectionID = $pdo->lastInsertId();
            }
            $lineParams = [ $section["SectionID"] ];
            // load the max sort order for the lines for this home info section
            $lineMaxSortOrderStmnt->execute($lineParams);
            $nextLineSortOrder = 0;
            $maxLineSorts = $lineMaxSortOrderStmnt->fetchAll();
            if (count($maxLineSorts) > 0) {
                $nextLineSortOrder = ((int)$maxLineSorts[0]["MaxSortOrder"]) + 1;
            }
            // load all the lines for this home info section
            $lineQueryStmnt->execute($lineParams);
            foreach ($lineQueryStmnt as $line) {
                // insert it into the HomeInfoLines table for the given just-created section
                $insertLineParams = [
                    $line["Name"],
                    $nextLineSortOrder++,
                    $createdSectionID
                ];
                $insertLineStmnt->execute($insertLineParams);
                $createdLineID = $pdo->lastInsertId();
                // load all the items for this line
                $itemParams = [ $line["HomeInfoLineID"] ];
                $itemQueryStmnt->execute($itemParams);
                foreach ($itemQueryStmnt as $item) {
                    // insert the new line item
                    $insertItemParams = [
                        $item["IsLink"],
                        $item["Text"],
                        $item["URL"],
                        $item["SortOrder"],
                        $createdLineID
                    ];
                    $insertItemStmnt->execute($insertItemParams);
                }
            }
        }
        // all done :3
    }

    function output_home_sections($sections, $isAdminPage, $conferenceID) {
        $lastSectionID = -1;
        $lastLineID = -1;
        foreach ($sections as $section) { 
            $sectionID = $section["SectionID"];
            $lineID = $section["LineID"];
            if ($lastSectionID !== $sectionID) {
                if ($lastSectionID !== -1) {
                    echo "</div></ul>";
                }
                $lastSectionID = $sectionID;
                echo "<div class='sortable-item' id='section-$lastSectionID'>";
                echo "<h5>" . $section["SectionName"] . "</h5>";
                if ($section["SectionSubtitle"] && trim($section["SectionSubtitle"]) !== "") {
                    echo "<h6>" . $section["SectionSubtitle"] . "</h6>";
                }
                $extraULClass = "";
                if ($isAdminPage) {
                    $extraULClass = "browser-default";
                    echo "<div class='section-buttons'>";
                        echo "<div class='row'>";
                            echo "<a class='add waves-effect waves-teal btn-flat teal-text col s12 m2 center-align' href='create-edit-section.php?type=update&id=$sectionID&conferenceID=$conferenceID'>Edit Section</a>";
                            echo "<a class='add waves-effect waves-teal btn-flat teal-text col s12 m2 center-align' href='view-home-section-items.php?sectionID=$sectionID&conferenceID=$conferenceID'>Edit Line Items</a>";
                            echo "<a class='add waves-effect waves-teal btn-flat red white-text col s12 m2 center-align' href='delete-section.php?id=$sectionID&conferenceID=$conferenceID'>Delete Section</a>";
                        echo "</div>";
                    echo "</div>";
                }
                echo "<ul class='section-items $extraULClass'>";
            }
            if ($section["Text"] != null) {
                $isFirstLineItem = false;
                if ($lastLineID !== $lineID) {
                    $isFirstLineItem = true;
                    if ($lastLineID !== -1) {
                        echo "</li>";
                    }
                    $lastLineID = $lineID;
                    echo "<li>";
                }
                if (!$isFirstLineItem) {
                    echo " - ";
                }
                if ($section["IsLink"]) {
                    $url = $section["URL"];
                    if (strpos($url, 'http://') === false && strpos($url, 'https://') === false) {
                        $url = "http://" . $url;
                    }
                    echo "<a href=\"" . $url . "\">" . $section["Text"] . "</a>";
                }
                else {
                    echo $section["Text"];
                }
            }
            else {
                // make sure we finish off the last line item
                if ($lastLineID !== -1) {
                    echo "</li>";
                }
                $lastLineID = -1;
            }
        }
        if ($lastLineID !== -1) {
            echo "</li>";
        }
        if ($lastSectionID !== -1) {
            echo "</ul>";
        }
        if (count($sections) > 0) {
            echo "</div>";
        }
    }

    function generate_quiz_questions($pdo, $params) {
        $shouldAvoidPastCorrectAnswers = filter_var($params["shouldAvoidPastCorrect"], FILTER_VALIDATE_BOOLEAN);
        
        $maxQuestions = $params["maxQuestions"];
        if ($maxQuestions > 500) {
            $maxQuestions = 500;
        }
        else if ($maxQuestions <= 0) {
            $maxQuestions = 10;
        }
        $maxPoints = $params["maxPoints"];
        if ($maxPoints > 500) {
            $maxPoints = 500;
        }
        else if ($maxPoints <= 0) {
            $maxPoints = 1;
        }
        
        $percentFillIn = 30;
        if (isset($params["fillInPercent"])) {
            $percentFillIn = filter_var($params["fillInPercent"], FILTER_VALIDATE_INT);
        }
        $percentFillIn = $percentFillIn / 100;

        $shouldShowOnlyRecentlyAdded = isset($params["flashShowOnlyRecent"]) ? filter_var($params["flashShowOnlyRecent"], FILTER_VALIDATE_BOOLEAN) : false;
        $recentlyAddedAmount = isset($params["flashShowOnlyRecentDayAmount"]) ? filter_var($params["flashShowOnlyRecentDayAmount"], FILTER_VALIDATE_INT, array("options" => array(
            "default" => 30,
            "min_range" => 1,
            "max_range" => 31
        ))) : 30;

        // question type values:
        // both
        // qa-only
        // fill-in-only
        $questionTypes = $params["questionTypes"];
        if ($shouldShowOnlyRecentlyAdded) { // override all user settings and load recent questions instead
            $questionTypes = "both"; 
            $questionOrder = "sequential-sequential";
            unset($params["quizItems"]);
            $shouldAvoidPastCorrectAnswers = false;
            $recentDayAmount = date('Y-m-d 00:00:00', strtotime('-' . $recentlyAddedAmount . ' days'));
        }
        $userWantsNormalQuestions = $params["questionTypes"] === "qa-only" || $params["questionTypes"] === "both";
        $userWantsFillIn = $params["questionTypes"] === "fill-in-only" || $params["questionTypes"] === "both";
        $questionOrder = $params["questionOrder"];
        $areRandomQuestionsPulled = $questionOrder == "random-sequential" || $questionOrder == "random-random";
        $isOutputSequential = $questionOrder == "random-sequential" || $questionOrder == "sequential-sequential";

        // see if user wants to load any possible question or just from a specific chapter of the Bible (or Bible commentary volume)
        if (!isset($params["quizItems"])) {
            $quizItems = array();
        }
        else {
            $quizItems = $params["quizItems"];
        }
        $chapterIDs = array();
        $commentaryIDs = array();
        if (count($quizItems) > 0) {
            // user wants to load specific things!
            // figure out which chapter IDs and volume numbers they want to load
            foreach ($quizItems as $item) {
                if (strpos($item, 'chapter-') !== false) {
                    $text = str_replace('chapter-', '', $item);
                    $chapterIDs[] = (int)$text;
                }
                else if (strpos($item, 'commentary-') !== false) {
                    $text = str_replace('commentary-', '', $item);
                    $commentaryIDs[] = (int)$text;
                }
            }
        }
        $shouldLoadBibleQnA = (count($quizItems) == 0 || count($chapterIDs) > 0) && $userWantsNormalQuestions;
        $shouldLoadCommentaryQnA = (count($quizItems) == 0 || count($commentaryIDs) > 0) && $userWantsNormalQuestions;
        $disableBibleFillInLoading = false;
        $disableCommentaryFillInLoading = false;
        if (count($chapterIDs) > 0 && count($commentaryIDs) == 0) {
            $shouldLoadCommentaryQnA = false;
            $disableCommentaryFillInLoading = true;
        }
        if (count($commentaryIDs) > 0 && count($chapterIDs) == 0) {
            $shouldLoadBibleQnA = false;
            $disableBibleFillInLoading = true;
        }
        // // // // //
        // load Bible questions
        // // // // //
        $currentYear = get_active_year($pdo)["YearID"];
        $bibleQnA = array();
        $selectPortion = '
            SELECT q.QuestionID, q.Type, Question, q.Answer, NumberPoints, DateCreated,
                bStart.Name AS StartBook, cStart.Number AS StartChapter, vStart.Number AS StartVerse,
                bEnd.Name AS EndBook, cEnd.Number AS EndChapter, vEnd.Number AS EndVerse,
                IFnull(uf.UserFlaggedID, 0) AS IsFlagged ';
        $fromPortion = '
            FROM Questions q 
                JOIN Verses vStart ON q.StartVerseID = vStart.VerseID
                JOIN Chapters cStart on vStart.ChapterID = cStart.ChapterID
                JOIN Books bStart ON bStart.BookID = cStart.BookID
                JOIN Languages l ON q.LanguageID = l.LanguageID

                LEFT JOIN Verses vEnd ON q.EndVerseID = vEnd.VerseID 
                LEFT JOIN Chapters cEnd on vEnd.ChapterID = cEnd.ChapterID 
                LEFT JOIN Books bEnd ON bEnd.BookID = cEnd.BookID 
                LEFT JOIN UserFlagged uf ON uf.QuestionID = q.QuestionID';
        if ($shouldAvoidPastCorrectAnswers) {
            $fromPortion .= ' LEFT JOIN UserAnswers ua ON ua.QuestionID = q.QuestionID '; 
        }
        $whereClause = ' 
            WHERE NumberPoints <= ' . $maxPoints . ' AND q.Type = "bible-qna"';
        if (count($chapterIDs) > 0) {
            $whereClause .= ' AND cStart.ChapterID IN (' . implode(',', $chapterIDs) . ') ';
        }
        if ($shouldAvoidPastCorrectAnswers) {
            $whereClause .= '  AND (ua.UserAnswerID IS null 
                OR (ua.UserAnswerID IS NOT null AND ua.WasCorrect = 0 AND ua.UserID = ' . $params["userID"] . '))'; 
        }
        if ($shouldShowOnlyRecentlyAdded) {
            $whereClause = ' WHERE q.Type = "bible-qna" AND DateCreated >= "' . $recentDayAmount . '" ';
        }

        $whereClause .= ' AND IsDeleted = 0 AND bStart.YearID = ' . $currentYear . ' AND (q.EndVerseID IS null OR bEnd.YearID = ' . $currentYear . ')';

        if ($params["languageID"] != -1 && is_numeric($params["languageID"])) {
            $whereClause .= " AND l.LanguageID = " . $params["languageID"];
        }

        $orderByPortion = '';
        if ($areRandomQuestionsPulled) {
            $orderByPortion = ' ORDER BY RAND() ';
        }
        else {
            // sequential-sequential
            $orderByPortion = '
                ORDER BY bStart.Name, cStart.Number, vStart.Number, bEnd.Name, cEnd.Number, vEnd.Number';
        }

        $limitPortion = ' LIMIT ' . $maxQuestions;
        if ($shouldLoadBibleQnA) {
            $stmt = $pdo->query($selectPortion . $fromPortion . $whereClause . $orderByPortion . $limitPortion);
            $bibleQnA = $stmt->fetchAll();
        }
        // // // // //
        // load Bible fill in the blank questions
        // // // // //
        $bibleFillIn = array();
        $whereClause = str_replace("bible-qna", "bible-qna-fill", $whereClause);
        if ($userWantsFillIn && !$disableBibleFillInLoading) {
            $stmt = $pdo->query($selectPortion . $fromPortion . $whereClause . $orderByPortion . $limitPortion);
            $bibleFillIn = $stmt->fetchAll();
        }

        // // // // //
        // load commentary questions
        // // // // //
        $commentaryQnA = array();
        $selectPortion = '
            SELECT q.QuestionID, q.Type, Question, q.Answer, NumberPoints, DateCreated,
                IFnull(uf.UserFlaggedID, 0) AS IsFlagged,
                comm.Number AS CommentaryNumber, CommentaryStartPage, CommentaryEndPage, comm.TopicName AS CommentaryTopic ';
        $fromPortion = '
            FROM Questions q 
                LEFT JOIN UserFlagged uf ON uf.QuestionID = q.QuestionID
                JOIN Commentaries comm ON q.CommentaryID = comm.CommentaryID
                JOIN Languages l ON q.LanguageID = l.LanguageID';
        if ($shouldAvoidPastCorrectAnswers) {
            $fromPortion .= ' LEFT JOIN UserAnswers ua ON ua.QuestionID = q.QuestionID '; 
        }
        $whereClause = ' 
            WHERE NumberPoints <= ' . $maxPoints . ' AND q.Type = "commentary-qna"';
        if (count($commentaryIDs) > 0) {
            $whereClause .= ' AND comm.CommentaryID IN (' . implode(',', $commentaryIDs) . ') ';
        }
        if ($shouldAvoidPastCorrectAnswers) {
            $whereClause .= '  AND (ua.UserAnswerID IS null 
                OR (ua.UserAnswerID IS NOT null AND ua.WasCorrect = 0 AND ua.UserID = ' . $params["userID"] . '))'; 
        }
        if ($shouldShowOnlyRecentlyAdded) {
            $whereClause = ' WHERE q.Type = "commentary-qna" AND DateCreated >= "' . $recentDayAmount . '" ';
        }
        $whereClause .= ' AND IsDeleted = 0 AND comm.YearID = ' . $currentYear;

        if ($params["languageID"] != -1 && is_numeric($params["languageID"])) {
            $whereClause .= " AND l.LanguageID = " . $params["languageID"];
        }

        if (!$areRandomQuestionsPulled) {
            $orderByPortion = ' ORDER BY CommentaryNumber, CommentaryStartPage, CommentaryEndPage';
        }
        if ($shouldLoadCommentaryQnA) {
            $stmt = $pdo->query($selectPortion . $fromPortion . $whereClause . $orderByPortion . $limitPortion);
            $commentaryQnA = $stmt->fetchAll();
        }
        // // // // //
        // load commentary fill in the blank questions
        // // // // //
        $commentaryFillIn = array();
        $whereClause = str_replace("commentary-qna", "commentary-qna-fill", $whereClause);
        if ($userWantsFillIn && !$disableCommentaryFillInLoading) {
            $stmt = $pdo->query($selectPortion . $fromPortion . $whereClause . $orderByPortion . $limitPortion);
            $commentaryFillIn = $stmt->fetchAll();
        }
        // // // // //
        // Merge data as needed
        // // // // //
        if ($isOutputSequential) {
            // If things need to be shown sequentially, we need to sort them individually,
            // then re-merge them in a random order (but still sequential within the question types)

            // Sort the arrays
            // https://stackoverflow.com/a/3233009/3938401
            array_multisort(
                array_column($bibleQnA, 'StartBook'), SORT_ASC, 
                array_column($bibleQnA, 'StartChapter'), SORT_ASC, 
                array_column($bibleQnA, 'StartVerse'), SORT_ASC,
                array_column($bibleQnA, 'EndBook'), SORT_ASC,
                array_column($bibleQnA, 'EndChapter'), SORT_ASC,
                array_column($bibleQnA, 'EndVerse'), SORT_ASC,
                array_column($bibleQnA, 'QuestionID'), SORT_ASC,
                $bibleQnA);
            array_multisort(
                array_column($bibleFillIn, 'StartBook'), SORT_ASC, 
                array_column($bibleFillIn, 'StartChapter'), SORT_ASC, 
                array_column($bibleFillIn, 'StartVerse'), SORT_ASC,
                array_column($bibleFillIn, 'EndBook'), SORT_ASC,
                array_column($bibleFillIn, 'EndChapter'), SORT_ASC,
                array_column($bibleFillIn, 'EndVerse'), SORT_ASC,
                array_column($bibleFillIn, 'QuestionID'), SORT_ASC,
                $bibleFillIn);
                
            array_multisort(
                array_column($commentaryQnA, 'CommentaryNumber'), SORT_ASC, 
                array_column($commentaryQnA, 'CommentaryTopic'), SORT_ASC, 
                array_column($commentaryQnA, 'CommentaryStartPage'), SORT_ASC, 
                array_column($commentaryQnA, 'CommentaryEndPage'), SORT_ASC,
                array_column($commentaryQnA, 'QuestionID'), SORT_ASC,
                $commentaryQnA);
            array_multisort(
                array_column($commentaryFillIn, 'CommentaryNumber'), SORT_ASC, 
                array_column($commentaryFillIn, 'CommentaryTopic'), SORT_ASC, 
                array_column($commentaryFillIn, 'CommentaryStartPage'), SORT_ASC, 
                array_column($commentaryFillIn, 'CommentaryEndPage'), SORT_ASC,
                array_column($commentaryFillIn, 'QuestionID'), SORT_ASC,
                $commentaryFillIn);
        }
        
        // Generate final questions array using data we've pulled out of the database
        $output = array();
        $bibleCount = count($bibleQnA);
        $bibleFillInCount = count($bibleFillIn);
        $commentaryCount = count($commentaryQnA);
        $commentaryFillInCount = count($commentaryFillIn);
        $bibleAdded = 0;
        $bibleFillInAdded = 0;
        $commentaryAdded = 0;
        $commentaryFillInAdded = 0;
        //die("bible: " . (int)$bibleCount . "  commentary: " . (int)$commentaryCount);
        $bibleIndex = 0;
        $bibleFillInIndex = 0;
        $commentaryIndex = 0;
        $commentaryFillInIndex = 0;
        for ($i = 0; $i < $maxQuestions; $i++) {
            $hasBibleQuestionLeft = $bibleIndex < $bibleCount;
            $hasBibleFillInLeft = $bibleFillInIndex < $bibleFillInCount;
            $hasCommentaryQuestionLeft = $commentaryIndex < $commentaryCount;
            $hasCommentaryFillInQuestionLeft = $commentaryFillInIndex < $commentaryFillInCount;

            if (!$hasBibleQuestionLeft && !$hasCommentaryQuestionLeft && 
                !$hasBibleFillInLeft && !$hasCommentaryFillInQuestionLeft) {
                break; // ran out of questions!
            }
            // figure out which arrays have stuff left
            $availableArraysOfQuestions = array();
            if ($hasBibleQuestionLeft) {
                $availableArraysOfQuestions[] = "bible-qna";
            }
            if ($hasBibleFillInLeft) {
                $availableArraysOfQuestions[] = "bible-qna-fill";
            }
            if ($hasCommentaryQuestionLeft) {
                $availableArraysOfQuestions[] = "commentary-qna";
            }
            if ($hasCommentaryFillInQuestionLeft) {
                $availableArraysOfQuestions[] = "commentary-qna-fill";
            }
            // now choose one
            $index = random_int(0, count($availableArraysOfQuestions) - 1);
            $typeToAdd = $availableArraysOfQuestions[$index];
            // add the question to the output
            if ($typeToAdd == "bible-qna") {
                $output[] = $bibleQnA[$bibleIndex];
                $bibleIndex++;
                $bibleAdded++;
            }
            else if ($typeToAdd == "bible-qna-fill") {
                $output[] = $bibleFillIn[$bibleFillInIndex];
                $bibleFillInIndex++;
                $bibleFillInAdded++;
            }
            else if ($typeToAdd == "commentary-qna") {
                $output[] = $commentaryQnA[$commentaryIndex];
                $commentaryIndex++;
                $commentaryAdded++;
            }
            else if ($typeToAdd == "commentary-qna-fill") {
                $output[] = $commentaryFillIn[$commentaryFillInIndex];
                $commentaryFillInIndex++;
                $commentaryFillInAdded++;
            }
        }
        // set questions to output of this little merging algorithm
        $questions = $output;
        
        // TODO: sort/merge with fill in the blank questions?
        // load non-blankable words
        $words = BlankableWord::loadAllBlankableWords($pdo);
        // Generate output
        $outputQuestions = [];
        $number = 1;
        foreach ($questions as $question) {
            $data = array (
                "type" => $question["Type"],
                "number" => $number,
                "id" => $question["QuestionID"],
                "isFlagged" => $question["IsFlagged"],
                "points" => $question["NumberPoints"],
                "question" => trim($question["Question"]),
                "answer" => trim($question["Answer"])
            );
            if (Question::isTypeBibleQnA($question["Type"])) {
                // Bible Q&A
                $data["startBook"] = $question["StartBook"] != null ? $question["StartBook"] : "";
                $data["startChapter"] = $question["StartChapter"] != null ? $question["StartChapter"] : "";
                $data["startVerse"] = $question["StartVerse"] != null ? $question["StartVerse"] : "";
                $data["endBook"] = $question["EndBook"] != null ? $question["EndBook"] : "";
                $data["endChapter"] = $question["EndChapter"] != null ? $question["EndChapter"] : "";
                $data["endVerse"] = $question["EndVerse"] != null ? $question["EndVerse"] : "";
            }
            else if (Question::isTypeCommentaryQnA($question["Type"])) {
                // commentary Q&A
                $data["volume"] = $question["CommentaryNumber"];
                $data["topic"] = $question["CommentaryTopic"];
                $data["startPage"] = $question["CommentaryStartPage"];
                $data["endPage"] = $question["CommentaryEndPage"];
            }
            if (Question::isTypeFillIn($question["Type"])) {
                $fillInData = BlankableWord::generateFillInQuestion(trim($question["Question"]), $percentFillIn, $words);
                $data["fillInData"] = $fillInData["data"];
                $data["points"] = $fillInData["blank-count"];
            }
            // for fill in the blank, will have text/blank key/value pairs
            $outputQuestions[] = $data;
            $number++;
        }

        $output = [ 
            "bibleQuestions" => $bibleAdded,
            "bibleFillIns" => $bibleFillInAdded,
            "commentaryQuestions" => $commentaryAdded,
            "commentaryFillIns" => $commentaryFillInAdded,
            "totalQuestions" => ($bibleAdded + $bibleFillInAdded + $commentaryAdded + $commentaryFillInAdded),
            "questions" => $outputQuestions 
        ];

        return $output;
    }

    function generate_weighted_quiz_questions($pdo, $params) {
        $DEBUG = false;
        // performing custom question distribution!
        $bibleWeights = [];
        $commentaryWeights = [];
        $allWeights = [];
        foreach ($params as $key => $value) {
            if (str_contains("table-input-chapter-", $key)) {
                $bibleWeights[$key] = $value;
                $allWeights[$key] = $value;
            }
            else if (str_contains("table-input-commentary-", $key)) {
                $commentaryWeights[$key] = $value;
                $allWeights[$key] = $value;
            }
        }
        // Make sure submitted percentage not below 0 or above 100
        $totalPercent = 0;
        $hasNegativePercent = false;
        foreach($bibleWeights as $key => $value) {
            $totalPercent += (int)$value;
            if ((int)$value < 0) {
                $hasNegativePercent = true;
            }
        }
        foreach($commentaryWeights as $key => $value) {
            $totalPercent += (int)$value;
            if ((int)$value < 0) {
                $hasNegativePercent = true;
            }
        }
        if ($hasNegativePercent) {
            die("Invalid weighted question percent given. All percents must be above positive or 0.");
        }
        if ($totalPercent < 0 || $totalPercent > 100) {
            die("Invalid weighted question percent given. Value must be between 0 and 100 inclusive.");
        }
        //print_r($params["questionTypes"]);
        // // // //
        // For each quizItems item that has a specific weight set in bibleWeights/commentaryWeights,
        // generate questions.
        $allGenerated = [];
        $quizItems = $params["quizItems"];
        $postCopy = $params;
        $totalGenerated = 0;
        // get rid of values we will override in the loop
        unset($postCopy["quizItems"]);
        $maxQuestions = (int)$postCopy["maxQuestions"];
        unset($postCopy["maxQuestions"]);
        // first generate questions for those sections that have weights
        for ($i = 0; $i < count($quizItems); $i++) {
            $quizItem = $quizItems[$i];
            $allWeightsKey = "table-input-" . $quizItem;
            if (isset($allWeights[$allWeightsKey]) && (int)$allWeights[$allWeightsKey] > 0) {
                $postCopy["quizItems"] = [ $quizItem ];
                $quizItemCount = count($quizItems); // count every loop iteration due to the unset a few lines down
                if ($i == $quizItemCount - 1 && $quizItemCount == 1) {
                    // if we only have 1 thing left with our weighting system and
                    // this thing is weighted, try to get as many questions out of it as possible
                    $postCopy["maxQuestions"] = $maxQuestions - $totalGenerated;
                }
                else {
                    $postCopy["maxQuestions"] = floor($maxQuestions * ((int)$allWeights[$allWeightsKey] / 100));
                    if ($postCopy["maxQuestions"] == 0) {
                        $postCopy["maxQuestions"] = 1;
                    }
                    if ($postCopy["maxQuestions"] == 1 && $postCopy["maxQuestions"] + $totalGenerated > $maxQuestions) {
                        break;
                    }
                }
                $generatedQuestions = generate_quiz_questions($pdo, $postCopy);
                $allGenerated[] = $generatedQuestions;
                $totalGenerated += (int)$generatedQuestions["totalQuestions"];
                // we don't want to generate questions for this chapter again
                unset($quizItems[$i]);
            }
        }
        //echo "-------" . "\n";
        //echo $totalGenerated . " questions generated \n";
        //echo "-------" . "\n";
        // OK, now that we have generated questions for all sections with specific
        // weights, generate as many questions as possible for the remaining sections
        $questionsLeft = $maxQuestions - $totalGenerated;
        //echo "There are " . $questionsLeft .  " questions left to generate \n";
        
        // need to know how to pull out questions and sort questions at the end.
        $questionOrder = $params["questionOrder"];
        $areRandomQuestionsPulled = $questionOrder == "random-sequential" || $questionOrder == "random-random";
        $isOutputSequential = $questionOrder == "random-sequential" || $questionOrder == "sequential-sequential";

        if ($questionsLeft > 0) {
            $numberOfQuestionsForEachPortion = round($questionsLeft / count($quizItems));
            if ($numberOfQuestionsForEachPortion == 0) {
                $numberOfQuestionsForEachPortion = 1;
            }
            //echo "There are " . $numberOfQuestionsForEachPortion .  " questions for each item to generate \n";

            // OK INSTEAD ALWAYS GENERATE $QUESTIONSLEFT 
            // THEN SORT BY # GENERATED LEAST TO GREATEST
            // THEN TRY TO GET $numberOfQuestionsForEachPortion FROM EACH ONE
            // IF YOU CAN'T, JUST GRAB AS MANY AS POSSIBLE FROM ONES AS YOU GO ALONG THE ARRAY

            $otherGenerated = [];
            foreach ($quizItems as $quizItem) { // take whatever is left
                $postCopy["quizItems"] = [ $quizItem ];
                $postCopy["maxQuestions"] = $questionsLeft;
                $generatedQuestions = generate_quiz_questions($pdo, $postCopy);
                $otherGenerated[] = $generatedQuestions;
                //echo "Got " . $generatedQuestions["totalQuestions"] . " out\n";
            }
            // sort $otherGenerated by count from least to greatest
            array_multisort(array_column($otherGenerated, 'totalQuestions'), SORT_ASC, $otherGenerated);
            // now that we are sorted, grab questions from each one until we have enough
            // ugh we have to grab random or sequentially depending on what user asked for
            for ($i = 0; $i < count($otherGenerated); $i++) {
                $item = $otherGenerated[$i];
                if ($i == count($otherGenerated) - 1) {
                    $numberOfQuestionsForEachPortion = $questionsLeft; // try to get as many from last one as possible
                }
                if ($item["totalQuestions"] < $numberOfQuestionsForEachPortion) {
                    // we can't get enough questions out of this item. we'll have to adjust
                    // $numberOfQuestionsForEachPortion to account for this
                    $questionsLeft -= $item["totalQuestions"];
                    if ($i != count($otherGenerated) - 1) {
                        $numberOfQuestionsForEachPortion = round($questionsLeft / (count($quizItems) - ($i + 1)));
                        if ($numberOfQuestionsForEachPortion == 0) {
                            $numberOfQuestionsForEachPortion = 1;
                        }
                    }
                    // else we just flat out don't have enough questions. Sorry.
                    // don't have to worry about random selection or in order selection as we are choosing all of them,
                    // so just add these questions to the overall output.
                    $totalGenerated += $item["totalQuestions"];
                    $allGenerated[] = $item;
                    //echo "Added " . $item["totalQuestions"] . " when I did not have enough\n";
                }
                else {
                    // we have enough to fulfill our needs!
                    // pick out $numberOfQuestionsForEachPortion questions
                    $pickedQuestions = [];
                    if ($areRandomQuestionsPulled) {
                        shuffle($item["questions"]);
                        $pickedQuestions = array_slice($item["questions"], 0, $numberOfQuestionsForEachPortion);
                    }
                    else {
                        // pick the first $numberOfQuestionsForEachPortion out of the questions array
                        // as they are already in sequential order
                        $pickedQuestions = array_slice($item["questions"], 0, $numberOfQuestionsForEachPortion);
                    }
                    $questionsLeft -= $numberOfQuestionsForEachPortion;
                    $totalGenerated += $numberOfQuestionsForEachPortion;
                    $bibleQuestions = 0;
                    $bibleFillIns = 0;
                    $commentaryQuestions = 0;
                    $commentaryFillIns = 0;
                    foreach ($pickedQuestions as $question) {
                        if ($question["type"] == "bible-qna") {
                            $bibleQuestions++;
                        }
                        else if ($question["type"] == "bible-qna-fill") {
                            $bibleFillIns++;
                        }
                        else if ($question["type"] == "commentary-qna") {
                            $commentaryQuestions++;
                        }
                        else if ($question["type"] == "commentary-qna-fill") {
                            $commentaryFillIns++;
                        }
                    }
                    $allGenerated[] = [
                        "bibleQuestions" => $bibleQuestions,
                        "bibleFillIns" => $bibleFillIns,
                        "commentaryQuestions" => $commentaryQuestions,
                        "commentaryFillIns" => $commentaryFillIns,
                        "totalQuestions" => $numberOfQuestionsForEachPortion,
                        "questions" => $pickedQuestions
                    ];
                }
                //echo "There are " . $questionsLeft .  " questions left to generate \n";
                if ($questionsLeft <= 0) {
                    break; // just in case...
                }
            }
        }
        // At this point, everything is generated! Huzzah! Collate the questions into 
        // the final output, make sure things are sorted properly, and then return the
        // final data!
        $output = [
            "bibleQuestions" => 0,
            "bibleFillIns" => 0,
            "commentaryQuestions" => 0,
            "commentaryFillIns" => 0,
            "totalQuestions" => 0,
            "questions" => []
        ];
        foreach ($allGenerated as $item) {
            $output["bibleQuestions"] += (int)$item["bibleQuestions"];
            $output["bibleFillIns"] += (int)$item["bibleFillIns"];
            $output["commentaryQuestions"] += (int)$item["commentaryQuestions"];
            $output["commentaryFillIns"] += (int)$item["commentaryFillIns"];
            $output["totalQuestions"] += (int)$item["totalQuestions"];
            $output["questions"] = array_merge($output["questions"], $item["questions"]);
        }
        //echo "-------" . "\n";
        //echo "After all done, " . $totalGenerated . " questions generated \n";
        //echo "After all done, " . count($output["questions"]) . " questions generated \n";
        //echo "There are " . $questionsLeft .  " questions left to generate \n";
        //echo "-------" . "\n";
        
        // ok, everything is generated. However, now we need to resort 
        // everything so that the output is what the user expects!

        if ($isOutputSequential) {
            // have to break questions out into each kind and then sort and then reorder them
            $totalQuestions = count($output["questions"]);
            $bibleQnA = [];
            $bibleFillIn = [];
            $commentaryQnA = [];
            $commentaryFillIn = [];
            foreach ($output["questions"] as $question) {
                if ($question["type"] == "bible-qna") {
                    $bibleQnA[] = $question;
                }
                else if ($question["type"] == "bible-qna-fill") {
                    $bibleFillIn[] = $question;
                }
                else if ($question["type"] == "commentary-qna") {
                    $commentaryQnA[] = $question;
                }
                else if ($question["type"] == "commentary-qna-fill") {
                    $commentaryFillIn[] = $question;
                }
            }
            // sort!
            array_multisort(
                array_column($bibleQnA, 'startBook'), SORT_ASC, 
                array_column($bibleQnA, 'startChapter'), SORT_ASC, 
                array_column($bibleQnA, 'startVerse'), SORT_ASC,
                array_column($bibleQnA, 'endBook'), SORT_ASC,
                array_column($bibleQnA, 'endChapter'), SORT_ASC,
                array_column($bibleQnA, 'endVerse'), SORT_ASC,
                array_column($bibleQnA, 'id'), SORT_ASC,
                $bibleQnA);
            array_multisort(
                array_column($bibleFillIn, 'startBook'), SORT_ASC, 
                array_column($bibleFillIn, 'startChapter'), SORT_ASC, 
                array_column($bibleFillIn, 'startVerse'), SORT_ASC,
                array_column($bibleFillIn, 'endBook'), SORT_ASC,
                array_column($bibleFillIn, 'endChapter'), SORT_ASC,
                array_column($bibleFillIn, 'endVerse'), SORT_ASC,
                array_column($bibleFillIn, 'id'), SORT_ASC,
                $bibleFillIn);
            array_multisort(
                array_column($commentaryQnA, 'number'), SORT_ASC, 
                array_column($commentaryQnA, 'topic'), SORT_ASC, 
                array_column($commentaryQnA, 'startPage'), SORT_ASC, 
                array_column($commentaryQnA, 'endPage'), SORT_ASC,
                array_column($commentaryQnA, 'id'), SORT_ASC,
                $commentaryQnA);
            array_multisort(
                array_column($commentaryFillIn, 'number'), SORT_ASC, 
                array_column($commentaryFillIn, 'topic'), SORT_ASC, 
                array_column($commentaryFillIn, 'startPage'), SORT_ASC, 
                array_column($commentaryFillIn, 'endPage'), SORT_ASC,
                array_column($commentaryFillIn, 'id'), SORT_ASC,
                $commentaryFillIn);
            // now mash them back into a quiz.
            $reorderedQuestions = [];
            $bibleIndex = 0;
            $bibleFillInIndex = 0;
            $commentaryIndex = 0;
            $commentaryFillInIndex = 0;
            $bibleCount = count($bibleQnA);
            $bibleFillInCount = count($bibleFillIn);
            $commentaryCount = count($commentaryQnA);
            $commentaryFillInCount = count($commentaryFillIn);
            for ($i = 0; $i < $totalQuestions; $i++) {
                $hasBibleQuestionLeft = $bibleIndex < $bibleCount;
                $hasBibleFillInLeft = $bibleFillInIndex < $bibleFillInCount;
                $hasCommentaryQuestionLeft = $commentaryIndex < $commentaryCount;
                $hasCommentaryFillInQuestionLeft = $commentaryFillInIndex < $commentaryFillInCount;

                $availableArraysOfQuestions = [];
                if ($hasBibleQuestionLeft) {
                    $availableArraysOfQuestions[] = "bible-qna";
                }
                if ($hasBibleFillInLeft) {
                    $availableArraysOfQuestions[] = "bible-qna-fill";
                }
                if ($hasCommentaryQuestionLeft) {
                    $availableArraysOfQuestions[] = "commentary-qna";
                }
                if ($hasCommentaryFillInQuestionLeft) {
                    $availableArraysOfQuestions[] = "commentary-qna-fill";
                }
                //echo "i = " . $i . "\n";
                //echo "bible = " . $bibleIndex . ", bible fill = " . $bibleFillInIndex . ", commentary = " . $commentaryIndex . ", comm fill = ". $commentaryFillInIndex . "\n";
                $index = random_int(0, count($availableArraysOfQuestions) - 1);
                $typeToAdd = $availableArraysOfQuestions[$index];
                if ($typeToAdd == "bible-qna") {
                    $reorderedQuestions[] = $bibleQnA[$bibleIndex++];
                }
                else if ($typeToAdd == "bible-qna-fill") {
                    $reorderedQuestions[] = $bibleFillIn[$bibleFillInIndex++];
                }
                else if ($typeToAdd == "commentary-qna") {
                    $reorderedQuestions[] = $commentaryQnA[$commentaryIndex++];
                }
                else if ($typeToAdd == "commentary-qna-fill") {
                    $reorderedQuestions[] = $commentaryFillIn[$commentaryFillInIndex++];
                }
            }
            $output["questions"] = $reorderedQuestions;
        }
        else {
            // random output! shuffle 2x (2x because 1x is boring)
            shuffle($output["questions"]);
            shuffle($output["questions"]);
        }
        //print_r($output);
        if ($DEBUG) {
            $thingsUsed = [];
            foreach ($output["questions"] as $question) {
                if (str_contains("bible", $question["type"])) {
                    $key = $question["startBook"] . " " . $question["startChapter"];
                    if (!isset($thingsUsed[$key])) {
                        $thingsUsed[$key] = 0;
                    }
                    $thingsUsed[$key] += 1;
                }
                else {
                    $key = "Commentary " . $question["volume"] . " - " . $question["topic"];
                    if (!isset($thingsUsed[$key])) {
                        $thingsUsed[$key] = 0;
                    }
                    $thingsUsed[$key] += 1;
                }
            }
            foreach ($thingsUsed as $key => $value) {
                echo $key . " --- " . $value . "\n";
            }
            die();
        }
        return $output;
    }

?>