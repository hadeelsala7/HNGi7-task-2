<?php

// Listen to URL query param if JSON output is enabled
$query = $_SERVER["QUERY_STRING"] ?? '';
$jsonEnabled = isset($query) && $query == 'json';


/**
 * Run every file in a directory This is our main function
 * 
 * @param $path The file path to check
 * 
 * @var object $totalOutput : contains two arrays valid and invalid
 * @var int $internsSubmitted : total number of files in the path, used for total interns submission
 * 
 * @return array an array of $totalOutput, $internsSubmitted, $totalPass, $totalFail
 * 
 */
function outputFiles($path)
{
    // totalOutput contains 2 arrays - valid (for valid file types), invalid (for unsupported file types)
    $totalOutput = ["valid" => [], "invalid" => []];
    $internsSubmitted = 0;

    // Check directory exists or not
    if (file_exists($path) && is_dir($path)) {
        // Scan the files in this directory
        $result = scandir($path);
        // Filter out the current (.) and parent (..) directories
        $files = array_diff($result, array('.', '..'));
        if (count($files) > 0) {
            // Loop through return array
            foreach ($files as $file) {
                $filePath = "$path/$file";
                // increase the internsSubmitted
                $internsSubmitted += 1;
                if (is_file($filePath)) {
                    // Split the filename
                    $fileExtension = getFileExtension($file);
                    if ($fileExtension) {
                        switch ($fileExtension) {
                            case 'js':
                                $scriptOut = run_script("node $filePath 2>&1", "Javascript");
                                array_push($totalOutput['valid'], $scriptOut);
                                break;

                            case 'py':
                                $scriptOut = run_script("python3 $filePath 2>&1", "Python");
                                array_push($totalOutput['valid'], $scriptOut);
                                break;

                            case 'php':
                                $scriptOut = run_script("php $filePath 2>&1", "PHP");
                                array_push($totalOutput['valid'], $scriptOut);
                                break;

                            default:
                                echo "'$file' is not valid \n";
                                array_push($totalOutput['invalid'], $file);
                                break;
                        }
                    }
                }
            }
        }
    }
    list($totalPass, $totalFail) = getPassedAndFailed($totalOutput);
    return array($totalOutput, $internsSubmitted, $totalPass, $totalFail);
}

/** 
 * Get a given file extension
 * The idea behind this is split a string and always take the last index
 * of the array. No matter how complex the file naming is, he extension is
 * always the last. You can always split string and iterate to the end and get the last index
 * or split the string, reverse the string and pick the first index
 * 
 * @param string $file : the given file name to check
 * @param array $tmp : the array got after splitting the string by '.' delimeter
 * @param string $extension : the file name extension
 * 
 * @return ?string returns a string of an extension or null
 * */
function getFileExtension($file)
{
    $tmp = explode(".", $file);
    $extension = end($tmp);
    return $extension ? $extension : null;
}

/**
 * Executes team member's scripts and returns an object with the required details
 * 
 * @param string $command : the command which is dependent on which script was detected
 * @param string $language : the language used for each script
 * 
 * @var array $scriptOutput : this returns an array objects which holds information about an intern and script status
 * @var string $bashOut : this holds the output string after the exec has been executed
 * @var string $status : The status got from checking the script output, it is either passed or failed
 * @var string $bashOutPart : This is a temporary variable used for splitting the $bashOut the get the name from $bashOut
 * @var string $fullName : This is the full name derived after continually splitting $bashOutPart
 * @var string $emailPattern : The regex for email matching, supports subdomain matching too
 * @var string $extractedMail : The email extracted from $bashOut with the help of $emailPattern, if empty, replace with null
 * @var string $hngIdPattern : The regex for HNG id matching
 * @var string $extractedMail : The HNG ID extracted from $bashOut with the help of $hngIdPattern, if empty, replace with null
 * @var string $replacedOutput : the output string where the email is removed
 * 
 * @return array An array of object containing a given intern information and script status
 * */
function run_script($command, string $language)
{

    $scriptOutput = [];
    $bashOut = exec($command);

    $status = getScriptOutputStatus($bashOut);

    // get full name
    $bashOutPart = explode(' with HNG', $bashOut)[0];
    $fullName = explode('this is ', $bashOutPart);

    // extract email
    $emailPattern = '/[a-z0-9_\-\+\.]+@[a-z0-9\-]+\.([a-z]{2,4})(?:\.[a-z]{2})?/i';
    $extractedMail = extractSubstring($emailPattern, $bashOut);
    $extractedMail = $extractedMail != "" ? $extractedMail : "null";

    // extract HNG ID
    $hngIdPattern = '/HNG-\d{3,}/i';
    $extractedHngIdPattern = extractSubstring($hngIdPattern, $bashOut);
    $extractedHngId = $extractedHngIdPattern != "" ? $extractedHngIdPattern : "null";

    // remove email from output
    if (strpos($bashOutPart, "Hello World") === 0 && $extractedMail != "null") {
        $wordsToReplace = "and email " . $extractedMail;
        $replacedOutput = removeString($bashOut, $wordsToReplace, "");
    }

    $scriptOutput['output'] = $replacedOutput;
    $scriptOutput['name'] = count($fullName) > 1 ? $fullName[1] : 'null';;
    $scriptOutput['id'] = $extractedHngId;
    $scriptOutput['email'] = strtolower($extractedMail);
    $scriptOutput['language'] = $language;
    $scriptOutput['status'] = $status;

    return $scriptOutput;
}

/** 
 * Pattern Matching for the string Output
 * 
 * @param string $output : The output from exec
 * @return string Passed : or Failed, and that depends if string of $output matches the Regex
 */
function getScriptOutputStatus($output)
{
    return preg_match('/^Hello\sWorld[,|.|!]?\sthis\sis\s[a-zA-Z\-]{2,}\s[a-zA-Z\-]{2,}(\s[a-zA-Z]{2,})?\swith\sHNGi7\sID\s(HNG-\d{3,})\sand\semail\s(([a-z0-9\+_\-]+)(\.[a-z0-9\+_\-]+)*@([a-z0-9\-]+\.)+[a-z]{2,6})\susing\s[a-zA-Z0-9|#]{2,}\sfor\sstage\s2\stask.?$/i', trim($output)) ? 'passed' : 'failed';
}

/**
 * Utility function to extract a substring from a
 * given string as long as the substring is matched in
 * the string
 * 
 * @param string $pattern : The pattern use for matching
 * @param string $inputString : The input string to check if substring exists
 * @return string $emailMatch : The string to be returned, it could be an empty string is substring doesn't exist
 */
function extractSubstring($pattern, $inputString)
{
    preg_match($pattern, $inputString, $emailMatch);
    return $emailMatch[0];
}

/**
 * Utility function to remove substrings from a string
 * 
 * @param string $originalString : the original string to modify
 * @param string $subString : The substring that is contained in the $originalString to be removed
 * @param string $replaceWith : The string that will replcae $subString
 * 
 * @return string $modified version of the $originalString
 */
function removeString($originalString, $subString, $replaceWith)
{
    return str_replace($subString, $replaceWith, $originalString);
}


/**
 * Get the number of passed and failed submission
 * This has a linear time complexity in our worst case
 * @param array $totalOutputProcessed:  this is all the processed output
 * 
 * @var array $validOutput : The ouuput of valid languages supported
 * @var array $invalidOutput : the output of invalid languages supported
 * @var int $totalPass : The total passed scripts
 * @var int $totalFail : The total failed scripts
 * 
 * @return array An array of total passed and total failed
 */
function getPassedAndFailed($totalOutputProcessed)
{
    $validOutput = $totalOutputProcessed['valid'];
    $invalidOutput = $totalOutputProcessed['invalid'];
    $totalPass = 0;
    $totalFail = 0;

    foreach ($validOutput as $output) {
        if ($output['status'] == 'passed') {
            $totalPass++;
        } elseif ($output['status'] == 'failed') {
            $totalFail++;
        }
    }

    foreach ($invalidOutput as $inout) {
        $totalFail++;
    }
    return array($totalPass, $totalFail);
}


// Call the outputFiles (it is the main function) function
list($outs, $totalInternsSubmitted, $totalPassOutput, $totalFailOutput) = outputFiles("scripts");

// preview the results
if ($jsonEnabled) {
    echo json_encode($outs);
} else {
?>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Document</title>
        <link href="https://fonts.googleapis.com/css2?family=Open+Sans:ital,wght@0,300;0,400;0,600;0,700;0,800;1,300;1,400;1,600;1,700;1,800&display=swap" />
        <style>
            * {
                margin: 0;
                padding: 0;
                font-family: 'Open Sans', sans-serif;
            }

            a {
                text-decoration: none;
            }

            body {
                background-color: #F9F9FA;
            }

            header {
                background: #ffffff;
                padding: 1.2rem 0 1.3rem;
                margin-bottom: 2rem;
            }

            header nav {
                max-width: 1440px;
                margin: 0 auto;
                padding: 0 5rem;
                display: -webkit-box;
                display: -ms-flexbox;
                display: flex;
                -webkit-box-pack: justify;
                -ms-flex-pack: justify;
                justify-content: space-between;
                -webkit-box-align: center;
                -ms-flex-align: center;
                align-items: center;
            }

            @media screen and (max-width: 634px) {
                header nav {
                    padding: 0 2rem;
                }
            }

            @media screen and (max-width: 234px) {
                header nav {
                    padding: 0 1rem;
                }
            }

            header nav a {
                font-weight: 600;
                font-size: 1.125rem;
                line-height: 25px;
                color: #333333;
                text-transform: uppercase;
            }

            header nav input {
                min-width: 417px;
                outline: none;
                border: 1px solid #828282;
                border-radius: 4px;
                padding: 9px 2rem 9px 2.3rem;
                background-image: url("https://res.cloudinary.com/theonlybakare/image/upload/v1591189231/search_kwamya.svg");
                background-repeat: no-repeat;
                background-position: top 9px left 12px;
            }

            @media screen and (max-width: 726px) {
                header nav input {
                    min-width: 217px;
                }
            }

            @media screen and (max-width: 428px) {
                header nav input {
                    display: none;
                }
            }

            .contents {
                max-width: 1400px;
                margin: 2rem auto 0;
                padding: 0 80px;
            }

            @media screen and (max-width: 634px) {
                .contents {
                    padding: 0 2rem;
                }
            }

            @media screen and (max-width: 536px) {
                .contents {
                    padding: 0 2rem;
                }
            }

            @media screen and (max-width: 234px) {
                .contents {
                    padding: 0 1rem;
                }
            }

            .contents .top-row {
                background-color: #FFFFFF;
                display: -webkit-box;
                display: -ms-flexbox;
                display: flex;
                -webkit-box-pack: space-evenly;
                -ms-flex-pack: space-evenly;
                justify-content: space-evenly;
                -webkit-box-align: center;
                -ms-flex-align: center;
                align-items: center;
                padding: 30px 0;
            }

            @media screen and (max-width: 536px) {
                .contents .top-row {
                    -webkit-box-orient: vertical;
                    -webkit-box-direction: normal;
                    -ms-flex-direction: column;
                    flex-direction: column;
                    -webkit-box-align: start;
                    -ms-flex-align: start;
                    align-items: flex-start;
                    padding: 1rem 1rem 0;
                }

                .contents .top-row p {
                    margin-bottom: 1rem;
                    display: -webkit-box;
                    display: -ms-flexbox;
                    display: flex;
                    -webkit-box-pack: justify;
                    -ms-flex-pack: justify;
                    justify-content: space-between;
                    width: 100%;
                }

                .contents .top-row p span {
                    margin-left: 0 !important;
                }
            }

            .contents .top-row p {
                font-size: 14px;
                line-height: 19px;
                color: #BDBDBD;
                text-transform: uppercase;
            }

            .contents .top-row p span {
                display: inline-block;
                margin-left: 1rem;
                font-weight: bold;
                color: #2F80ED;
            }

            .contents .top-row .passed span {
                color: #27AE60;
            }

            .contents .top-row .failed span {
                color: #EB5757;
            }

            .contents .log {
                background: #FFFFFF;
                margin-top: 1.5rem;
                padding: 1.5rem 2rem 1.75rem;
                border-bottom: 1px solid #E5E5E5;
            }

            .contents .log h2 {
                text-transform: uppercase;
                font-size: 1.125rem;
                line-height: 25px;
                color: #333333;
            }

            .contents .log .lead-wrapper {
                margin-top: 30px;
                display: -webkit-box;
                display: -ms-flexbox;
                display: flex;
                -webkit-box-align: center;
                -ms-flex-align: center;
                align-items: center;
            }

            @media screen and (max-width: 864px) {
                .contents .log .lead-wrapper {
                    -webkit-box-orient: vertical;
                    -webkit-box-direction: normal;
                    -ms-flex-direction: column;
                    flex-direction: column;
                    -webkit-box-align: start;
                    -ms-flex-align: start;
                    align-items: flex-start;
                }

                .contents .log .lead-wrapper div {
                    -webkit-box-pack: justify;
                    -ms-flex-pack: justify;
                    justify-content: space-between;
                }
            }

            .contents .log .lead-wrapper p {
                font-size: 14px;
                line-height: 19px;
                color: #333333;
                min-width: 77px;
            }

            .contents .log .lead-wrapper .lead {
                color: #2F80ED;
            }

            .contents .log .lead-wrapper div {
                width: 100%;
                display: -webkit-box;
                display: -ms-flexbox;
                display: flex;
                -webkit-box-pack: space-evenly;
                -ms-flex-pack: space-evenly;
                justify-content: space-evenly;
            }

            @media screen and (max-width: 864px) {
                .contents .log .lead-wrapper div {
                    margin-top: 1rem;
                    -webkit-box-pack: justify;
                    -ms-flex-pack: justify;
                    justify-content: space-between;
                }
            }

            @media screen and (max-width: 720px) {
                .contents .log .lead-wrapper div {
                    -webkit-box-orient: vertical;
                    -webkit-box-direction: normal;
                    -ms-flex-direction: column;
                    flex-direction: column;
                }

                .contents .log .lead-wrapper div p {
                    margin-top: 1rem;
                    display: -webkit-box;
                    display: -ms-flexbox;
                    display: flex;
                    -webkit-box-pack: justify;
                    -ms-flex-pack: justify;
                    justify-content: space-between;
                    -webkit-box-align: center;
                    -ms-flex-align: center;
                    align-items: center;
                }

                .contents .log .lead-wrapper div p span {
                    margin: 0;
                }
            }

            .contents .log .lead-wrapper div p span {
                font-weight: bold;
                margin-left: 1rem;
            }

            .contents .table-wrapper {
                position: relative;
                width: 100%;
                height: 600px;
                overflow-y: scroll;
                margin-bottom: 60px;
            }

            .contents .table-wrapper::-webkit-scrollbar {
                width: 4px;
                background-color: transparent;
            }

            .contents .table-wrapper::-webkit-scrollbar-thumb {
                width: 4px;
                background-color: #E0E0E0;
                border-radius: 5px;
            }

            .contents table {
                width: 100%;
                border-collapse: collapse;
            }

            @media screen and (max-width: 1162px) {
                .contents table {
                    width: 110%;
                }
            }

            @media screen and (max-width: 1040px) {
                .contents table {
                    width: 125%;
                }
            }

            @media screen and (max-width: 884px) {
                .contents table {
                    width: 150%;
                }
            }

            @media screen and (max-width: 804px) {
                .contents table {
                    width: 170%;
                }
            }

            @media screen and (max-width: 548px) {
                .contents table {
                    width: 200%;
                }
            }

            @media screen and (max-width: 482px) {
                .contents table {
                    width: 240%;
                }
            }

            @media screen and (max-width: 410px) {
                .contents table {
                    width: 280%;
                }
            }

            @media screen and (max-width: 372px) {
                .contents table {
                    width: 330%;
                }
            }

            .contents table tr {
                background: #ffffff;
                text-align: left;
            }

            .contents table tr th:first-child {
                padding-left: 2rem;
            }

            .contents table tr th:last-child {
                padding-right: 2rem;
            }

            .contents table tr th {
                background: #ffffff;
                padding: 1.5rem 0;
                position: -webkit-sticky;
                position: sticky;
                top: 0;
            }

            .contents table tbody {
                background-color: #FFFFFF;
            }

            .contents table tbody tr td {
                padding-bottom: 31px;
                font-size: 14px;
                line-height: 19px;
                color: #333333;
            }

            .contents table tbody tr td.b {
                background-color: #F9F9FA;
                padding: 0;
            }

            .contents table tbody tr .sn {
                padding-left: 2rem;
                max-width: 68px;
            }

            .contents table tbody tr .id {
                max-width: 196px;
            }

            .contents table tbody tr .name {
                max-width: 181px;
            }

            .contents table tbody tr .message {
                max-width: 400px;
            }

            .contents table tbody tr .status span {
                display: inline-block;
                border-radius: 4px;
                padding: 6px 0;
                color: white;
                font-weight: 600;
                min-width: 92px;
                text-align: center;
            }

            .contents table tbody tr .status .passed {
                background-color: #27AE60;
            }

            .contents table tbody tr .status .failed {
                background-color: #EB5757;
            }

            /* hidden class */
            .hidden {
                display: none;
            }
        </style>
    </head>

    <body>
        <header>
            <nav>
                <a href="#">team fierce</a>

                <input type="text" id="input" onkeyup="filter()" placeholder="Search" />
            </nav>
        </header>

        <section class="content-wrapper">
            <div class="contents">
                <div class="top-row">
                    <p>submitted: <span><?php echo ($totalInternsSubmitted) ?></span></p>
                    <p class="passed">passed: <span><?php echo ($totalPassOutput) ?></span></p>
                    <p class="failed">failed: <span><?php echo ($totalFailOutput) ?></span></p>
                </div>

                <div class="log">
                    <h2>logs</h2>

                    <div class="lead-wrapper">
                        <p class="lead">Team leads</p>
                        <div>
                            <p>Backend: <span>@kubiat</span></p>
                            <p>Frontend: <span>@delecoder</span></p>
                            <p>Devops: <span>@tomiwaajayi</span></p>
                        </div>
                    </div>
                </div>

                <div class="table-wrapper">
                    <table id="theTable">
                        <tr class="header">
                            <th>SN</th>
                            <th>Intern ID</th>
                            <th>Intern Name</th>
                            <th>Message</th>
                            <th>Status</th>
                        </tr>

                        <tbody>
                            <?php
                            $rowRecord = 1;
                            $outputRecord = $outs['valid'];
                            foreach ($outputRecord as $record) {
                                $peformanceStatus = $record['status'] == "passed" ? "Passed" : "Failed";
                                echo <<<EOL
                                            <tr>
                                                <td class="sn">$rowRecord</td>
                                                <td class="id">$record[id]</td>
                                                <td class="name">$record[name]</td>
                                                <td class="message">$record[output]</td>
                                                <td class="status"><span class=$record[status]>$peformanceStatus</span></td>
                                            </tr>
                                            EOL;
                                $rowRecord++;

                                // flush and buffer
                                flush();
                                ob_flush();
                                sleep(1);
                            } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
        <script>
            // console.log("hey")
            let table = document.getElementById("theTable");
            let rows = Array.prototype.slice.call(table.querySelectorAll("tr:not(.header)"));

            function filter() {
                // Always trim user input
                let filter = input.value.trim().toUpperCase();

                // Loop the rows
                rows.forEach(function(row) {

                    // You really don't need to know if the search criteria
                    // is in the first or second cell. You only need to know
                    // if it is in the row.
                    var data = "";
                    // Loop over all the cells in the current row and concatenate their text
                    Array.prototype.slice.call(row.getElementsByTagName("td")).forEach(function(r) {

                        data += r.textContent;
                    });

                    // Check the string for a match and show/hide row as needed
                    // Don't set individual styles. Add/remove classes instead
                    if (data.toUpperCase().indexOf(filter) > -1) {
                        // show row
                        row.classList.remove("hidden");
                    } else {
                        // hide row
                        row.classList.add("hidden");
                    }
                });

            }

            document.addEventListener("DOMContentLoaded", filter);
        </script>
    </body>

    </html>
<?php
}
?>