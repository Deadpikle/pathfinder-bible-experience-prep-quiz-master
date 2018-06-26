<?php

    require_once("blanks.php");

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

    function is_bible_qna($type) {
        return $type === "bible-qna" || $type == "bible-qna-fill";
    }

    function is_commentary_qna($type) {
        return $type === "commentary-qna" || $type == "commentary-qna-fill";
    }

    function is_fill_in($type) {
        return $type === "bible-qna-fill" || $type === "commentary-qna-fill";
    }

    // https://stackoverflow.com/a/834355/3938401
    function ends_with($haystack, $needle) {
        $length = strlen($needle);
        if ($length == 0) {
            return true;
        }

        return (substr($haystack, -$length) === $needle);
    }

    function generate_uuid() {
        $bytes = random_bytes(16);
        $UUID = bin2hex($bytes);
        // yay for laziness on the hyphen inserts! code from https://stackoverflow.com/a/33484855/3938401
        $UUID = substr($UUID, 0, 8) . '-' . 
                substr($UUID, 8, 4) . '-' . 
                substr($UUID, 12, 4) . '-' . 
                substr($UUID, 16, 4)  . '-' . 
                substr($UUID, 20);
        return $UUID;
    }

    function load_non_blankable_words($pdo) {
        $query = 'SELECT Word FROM BlankableWords ORDER BY Word';
        $stmt = $pdo->prepare($query);
        $stmt->execute([]);
        $words = $stmt->fetchAll();
        $output = array();
        foreach ($words as $word) {
            $output[] = $word["Word"];
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
            SELECT his.HomeInfoSectionID AS SectionID, his.Name AS SectionName, his.SortOrder AS SectionSortOrder,
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
        if ($bookQuestionData != NULL) {
            return $bookQuestionData['QuestionCount'];
        }
        return 0;
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
            SELECT his.HomeInfoSectionID AS SectionID, his.Name AS SectionName
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

        $insertSection = 'INSERT INTO HomeInfoSections (Name, SortOrder, YearID, ConferenceID) VALUES (?, ?, ?, ?)';
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
                $extraULClass = "";
                if ($isAdminPage) {
                    $extraULClass = "browser-default";
                    echo "<div class='section-buttons'>";
                        echo "<div class='row'>";
                            echo "<a class='add waves-effect waves-teal btn-flat teal-text col s12 m2 center-align' href='create-edit-section.php?type=update&id=$sectionID&conferenceID=$conferenceID'>Edit Section Name</a>";
                            echo "<a class='add waves-effect waves-teal btn-flat teal-text col s12 m2 center-align' href='view-home-section-items.php?sectionID=$sectionID&conferenceID=$conferenceID'>Edit Line Items</a>";
                            echo "<a class='add waves-effect waves-teal btn-flat red white-text col s12 m2 center-align' href='delete-section.php?id=$sectionID&conferenceID=$conferenceID'>Delete Section</a>";
                        echo "</div>";
                    echo "</div>";
                }
                echo "<ul class='section-items $extraULClass'>";
            }
            if ($section["Text"] != NULL) {
                $isFirstLineItem = FALSE;
                if ($lastLineID !== $lineID) {
                    $isFirstLineItem = TRUE;
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

        $shouldShowOnlyRecentlyAdded = isset($params["flashShowOnlyRecent"]) ? filter_var($params["flashShowOnlyRecent"], FILTER_VALIDATE_BOOLEAN) : FALSE;
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
            $shouldAvoidPastCorrectAnswers = FALSE;
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
        $disableBibleFillInLoading = FALSE;
        $disableCommentaryFillInLoading = FALSE;
        if (count($chapterIDs) > 0 && count($commentaryIDs) == 0) {
            $shouldLoadCommentaryQnA = FALSE;
            $disableCommentaryFillInLoading = TRUE;
        }
        if (count($commentaryIDs) > 0 && count($chapterIDs) == 0) {
            $shouldLoadBibleQnA = FALSE;
            $disableBibleFillInLoading = TRUE;
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
                IFNULL(uf.UserFlaggedID, 0) AS IsFlagged ';
        $fromPortion = '
            FROM Questions q 
                JOIN Verses vStart ON q.StartVerseID = vStart.VerseID
                JOIN Chapters cStart on vStart.ChapterID = cStart.ChapterID
                JOIN Books bStart ON bStart.BookID = cStart.BookID

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
            $whereClause .= '  AND (ua.UserAnswerID IS NULL 
                OR (ua.UserAnswerID IS NOT NULL AND ua.WasCorrect = 0 AND ua.UserID = ' . $params["userID"] . '))'; 
        }
        if ($shouldShowOnlyRecentlyAdded) {
            $whereClause = ' WHERE q.Type = "bible-qna" AND DateCreated >= "' . $recentDayAmount . '" ';
        }

        $whereClause .= ' AND IsDeleted = 0 AND bStart.YearID = ' . $currentYear . ' AND (q.EndVerseID IS NULL OR bEnd.YearID = ' . $currentYear . ')';

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
                IFNULL(uf.UserFlaggedID, 0) AS IsFlagged,
                comm.Number AS CommentaryNumber, CommentaryStartPage, CommentaryEndPage, comm.TopicName AS CommentaryTopic ';
        $fromPortion = '
            FROM Questions q 
                LEFT JOIN UserFlagged uf ON uf.QuestionID = q.QuestionID
                JOIN Commentaries comm ON q.CommentaryID = comm.CommentaryID';
        if ($shouldAvoidPastCorrectAnswers) {
            $fromPortion .= ' LEFT JOIN UserAnswers ua ON ua.QuestionID = q.QuestionID '; 
        }
        $whereClause = ' 
            WHERE NumberPoints <= ' . $maxPoints . ' AND q.Type = "commentary-qna"';
        if (count($commentaryIDs) > 0) {
            $whereClause .= ' AND comm.CommentaryID IN (' . implode(',', $commentaryIDs) . ') ';
        }
        if ($shouldAvoidPastCorrectAnswers) {
            $whereClause .= '  AND (ua.UserAnswerID IS NULL 
                OR (ua.UserAnswerID IS NOT NULL AND ua.WasCorrect = 0 AND ua.UserID = ' . $params["userID"] . '))'; 
        }
        if ($shouldShowOnlyRecentlyAdded) {
            $whereClause = ' WHERE q.Type = "commentary-qna" AND DateCreated >= "' . $recentDayAmount . '" ';
        }
        $whereClause .= ' AND IsDeleted = 0 AND comm.YearID = ' . $currentYear;

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
        $words = load_non_blankable_words($pdo);
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
            if (is_bible_qna($question["Type"])) {
                // Bible Q&A
                $data["startBook"] = $question["StartBook"] != NULL ? $question["StartBook"] : "";
                $data["startChapter"] = $question["StartChapter"] != NULL ? $question["StartChapter"] : "";
                $data["startVerse"] = $question["StartVerse"] != NULL ? $question["StartVerse"] : "";
                $data["endBook"] = $question["EndBook"] != NULL ? $question["EndBook"] : "";
                $data["endChapter"] = $question["EndChapter"] != NULL ? $question["EndChapter"] : "";
                $data["endVerse"] = $question["EndVerse"] != NULL ? $question["EndVerse"] : "";
            }
            else if (is_commentary_qna($question["Type"])) {
                // commentary Q&A
                $data["volume"] = $question["CommentaryNumber"];
                $data["topic"] = $question["CommentaryTopic"];
                $data["startPage"] = $question["CommentaryStartPage"];
                $data["endPage"] = $question["CommentaryEndPage"];
            }
            if (is_fill_in($question["Type"])) {
                $fillInData = generate_fill_in_question(trim($question["Question"]), $percentFillIn, $words);
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

?>