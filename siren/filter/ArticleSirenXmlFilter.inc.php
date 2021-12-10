<?php

/**
 * @file plugins/importexport/siren/filter/ArticleSirenXmlFilter.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ArticleSirenXmlFilter
 * @ingroup plugins_importexport_siren
 *
 * @brief Class that converts a Article to a Siren XML document.
 */

import('lib.pkp.classes.filter.PersistableFilter');

class ArticleSirenXmlFilter extends PersistableFilter {
	/**
	 * Constructor
	 * @param $filterGroup FilterGroup
	 */
	function __construct($filterGroup) {
		parent::__construct($filterGroup);
	}


	//
	// Implement template methods from PersistableFilter
	//
	/**
	 * @copydoc PersistableFilter::getClassName()
	 */
	function getClassName() {
		return 'plugins.importexport.siren.filter.ArticleSirenXmlFilter';
	}


	//
	// Implement abstract methods from SubmissionSirenXmlFilter
	//
	/**
	 * Get the representation export filter group name
	 * @return string
	 */
	function getRepresentationExportFilterGroupName() {
		return 'article-galley=>siren-xml';
	}

	//
	// Implement template methods from Filter
	//
	/**
	 * @see Filter::process()
	 * @param $submissions array Array of submissions
	 * @return DOMDocument
	 */

    function &process(&$submissions) {
//        print_r($submissions[0]->getContextId()) ;
        // Create the XML document
        $implementation = new DOMImplementation();
        $dtd = $implementation->createDocumentType('journal', '', 'http://www.w3.org/2001/XMLSchema');
        $doc = $implementation->createDocument('', '', $dtd);
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = true;

        $issueDao = DAORegistry::getDAO('IssueDAO'); /* @var $issueDao IssueDAO */
        $journalDao = DAORegistry::getDAO('JournalDAO'); /* @var $journalDao JournalDAO */
        $journal = null;

        $rootNode = $doc->createElement('journal');

        $titleidNode = $doc->createElement('titleid');
        $rootNode->appendChild($titleidNode)->appendChild($doc->createTextNode('47212961'));

        // Fetch associated objects
        if (!$journal || $journal->getId() != $submissions[0]->getContextId()) {
            $journal = $journalDao->getById($submissions[0]->getContextId());
        }

        // check various ISSN fields to create the ISSN tag
        if ($journal->getData('printIssn') != '') $issn = $journal->getData('printIssn');
        elseif ($journal->getData('issn') != '') $issn = $journal->getData('issn');
        else $issn = '';
        if ($issn != '') $rootNode->appendChild($doc->createElement('issn', $issn));

        if ($journal->getData('onlineIssn') != '') $issn = $journal->getData('onlineIssn');
        else $issn = '';
        if ($issn != '') $rootNode->appendChild($doc->createElement('eissn', $issn));

        /////////////////////////////
        $journalInfo = $doc->createElement('journalInfo');
        $journalInfo->setAttribute('lang','RUS');
        $rootNode->appendChild($journalInfo);

        $journalInfoTitle = $doc->createElement('title');
        $journalInfo ->appendChild($journalInfoTitle)->appendChild($doc->createTextNode('ПРИКЛАДНАЯ МАТЕМАТИКА & ФИЗИКА'));

        $issue = $issueDao->getBySubmissionId($submissions[0]->getId(), $journal->getId());
        $issueNode = $doc->createElement('issue');
        $rootNode->appendChild($issueNode);

        $volume=$doc->createElement('volume');
        if ($issue && $issue->getShowVolume()) $issueNode->appendChild($volume)->appendChild($doc->createTextNode($issue->getVolume()));

        $number=$doc->createElement('number');
        if ($issue && $issue->getShowNumber()) $issueNode->appendChild($number)->appendChild($doc->createTextNode($issue->getNumber()));


        $altNumber=$doc->createElement('altNumber');
        $issueNode->appendChild($altNumber);

//        $part=$doc->createElement('part');
//        $issueNode->appendChild($part);

        $pages=$doc->createElement('pages');
        $issueNode->appendChild($pages);

        $dateUni=$doc->createElement('dateUni');
        if ($issue->getDatePublished())
            $issueNode->appendChild($dateUni)->appendChild($doc->createTextNode(strftime('%Y', strtotime($issue->getDatePublished()))));



        $issTitle=$doc->createElement('issTitle');
        $issueNode->appendChild($issTitle);

        $codesNode = $doc->createElement('codes');
        $issueNode->appendChild($codesNode);

        $doiIssue = $doc->createElement('doi');
        $codesNode->appendChild($doiIssue);

        $articles = $doc->createElement('articles');
        $issueNode->appendChild($articles);


        $section = $doc ->createElement('section');
        $articles->appendChild($section);

        $secTitle = $doc->createElement('secTitle');
        $secTitle->setAttribute('lang','RUS');
        $section->appendChild($secTitle);

        foreach ($submissions as $submission) {
            if (!$journal || $journal->getId() != $submission->getContextId()) {
                $journal = $journalDao->getById($submission->getContextId());
            }

            $issue = $issueDao->getBySubmissionId($submission->getId(), $journal->getId());

            $articleNode = $doc->createElement('article');
            $articles->appendChild($articleNode);

            ////////////////////////////
            $artType = $doc->createElement('artType');
            $articleNode->appendChild($artType)->appendChild($doc->createTextNode('RAR'));

            ////////////////////////////
            $langPubl = $doc->createElement('langPubl');
            $articleNode->appendChild($langPubl)->appendChild($doc->createTextNode('RUS'));

            ////////////////////////////
            ///  Глобавльные данные о публикации
            $publication = $submission->getCurrentPublication();
            $localeTitle = $publication->getData('title');

            ////////////////////////////
            $artPpages = $doc->createElement('pages');
            $startPage = $publication->getStartingPage();
            //$endPage = $publication->getEndingPage();
            if (isset($startPage) && $startPage !== '') {
                $articleNode->appendChild($artPpages)->appendChild($doc->createTextNode($startPage));
                //$articleNode->appendChild($doc->createElement('LastPage'))->appendChild($doc->createTextNode($endPage));
            }

            ////////////////////////////
            if($publication->getData('authors')){
                $authorListNode = $doc->createElement('authors');
                $i=1;
                foreach ((array) $publication->getData('authors') as $author) {
                    $authorListNode->appendChild($this->generateAuthorNodeSiren($doc, $journal, $issue, $submission, $author, $i));
                    $i++;
                }
                $articleNode->appendChild($authorListNode);
            }


            ///////////////////////
            $artTitles = $doc->createElement('artTitles');
            $articleNode->appendChild($artTitles);
            $artTitleEN = $doc->createElement('artTitle');
            $artTitleEN-> setAttribute('lang', 'ENG');
            $artTitleRU = $doc->createElement('artTitle');
            $artTitleRU-> setAttribute('lang', 'RUS');
            $artTitles->appendChild($artTitleEN)->appendChild($doc->createTextNode($localeTitle['en_US']));
            $artTitles->appendChild($artTitleRU)->appendChild($doc->createTextNode($localeTitle['ru_RU']));

            /////////////////////////
            $abstrTitles = $doc->createElement('abstracts');
            $articleNode->appendChild($abstrTitles);
            $localeAbstrTitle = $publication->getData('abstract');
            $abstrTitleEN = $doc->createElement('abstract');
            $abstrTitleEN-> setAttribute('lang', 'ENG');
            $abstrTitleRU = $doc->createElement('abstract');
            $abstrTitleRU-> setAttribute('lang', 'RUS');
            $localeAbstrTitle['en_US'] = PKPString::html2text($localeAbstrTitle['en_US']);
            $localeAbstrTitle['ru_RU'] = PKPString::html2text($localeAbstrTitle['ru_RU']);
            $abstrTitles->appendChild($abstrTitleEN)->appendChild($doc->createTextNode($localeAbstrTitle['en_US']));
            $abstrTitles->appendChild($abstrTitleRU)->appendChild($doc->createTextNode($localeAbstrTitle['ru_RU']));

            /////////////////////////
            $locale = $publication->getData('locale');
            $lang = strtoupper(AppLocale::get3LetterFrom2LetterIsoLanguage(substr($locale, 0, 2)));
            $text=$doc->createElement('text');
            $text->setAttribute('lang', $lang);
            $articleNode->appendChild($text);

            /////////////////////////
            $this->generateCodesNode($doc, $articleNode, $publication);

            ////////////////////////
            $localeKeyword = $publication->getData('keywords');
            $localeKeywordsEN = $localeKeyword['en_US'];
            $localeKeywordsRU = $localeKeyword['ru_RU'];

            $keywords=$doc->createElement('keywords');
            $articleNode->appendChild($keywords);

            $kwdGroupEN=$doc->createElement('kwdGroup');
            $kwdGroupEN-> setAttribute('lang', 'ENG');
            $keywords->appendChild($kwdGroupEN);

            if (isset($localeKeywordsEN) && $localeKeywordsEN !== '') {
                foreach ($localeKeywordsEN as $localeKeywordEN) {
                    $keyword = $doc->createElement('keyword');
                    $kwdGroupEN->appendChild($keyword)->appendChild($doc->createTextNode($localeKeywordEN));
                }
            }else{
                $keyword = $doc->createElement('keyword');
                $kwdGroupEN->appendChild($keyword);
            }

            $kwdGroupRU=$doc->createElement('kwdGroup');
            $kwdGroupRU-> setAttribute('lang', 'RUS');
            $keywords->appendChild($kwdGroupRU);

            if (isset($localeKeywordsRU) && $localeKeywordsRU !== '') {
                foreach($localeKeywordsRU as $localeKeywordRU){
                    $keyword=$doc->createElement('keyword');
                    $kwdGroupRU->appendChild($keyword)->appendChild($doc->createTextNode($localeKeywordRU));
                }
            }else{
                $keyword=$doc->createElement('keyword');
                $kwdGroupRU->appendChild($keyword);
            }

            $citationsListNode = $this->createCitationsNode($doc, $publication);
            if ($citationsListNode) {
                $articleNode->appendChild($citationsListNode);
            }

            $filesNode=$doc->createElement('files');
            $articleNode->appendChild($filesNode);

            $file=$doc->createElement('file');
            $file-> setAttribute('desc', 'fullText');
            $filesNode->appendChild($file)->appendChild($doc->createTextNode('file_name'));

            $furl=$doc->createElement('furl');
            $filesNode->appendChild($furl);

            $rubricsNode=$doc->createElement('rubrics');
            $articleNode->appendChild($rubricsNode);
            $rubric=$doc->createElement('rubric');
            $rubricsNode->appendChild($rubric);

            $fundingsNode=$doc->createElement('fundings');
            $articleNode->appendChild($fundingsNode);

            $funding=$doc->createElement('funding');
            $funding-> setAttribute('lang', 'RUS');
            $fundingsNode->appendChild($funding);

            ////////////////// History
            $datesNode=$doc->createElement('dates');
            $articleNode->appendChild($datesNode);

            $dateReceived=$doc->createElement('dateReceived');
            $dateAccepted=$doc->createElement('dateAccepted');

            $datePublished = null;
            if ($submission) $datePublished = $submission->getCurrentPublication()->getData('datePublished');
            if (!$datePublished && $issue) $datePublished = $issue->getDatePublished();
            if ($datePublished) {
                $datesNode->appendChild($dateReceived)->appendChild($doc->createTextNode(date('d', strtotime($datePublished)).'.'.date('m', strtotime($datePublished)).
                    '.'.date('Y', strtotime($datePublished))));
                $datesNode->appendChild($dateAccepted)->appendChild($doc->createTextNode(date('d', strtotime($datePublished)).'.'.date('m', strtotime($datePublished)).
                            '.'.date('Y', strtotime($datePublished))));
            }

        }

        $issueFileNode=$doc->createElement('files');
        $issueNode->appendChild($issueFileNode);
        $issueFile=$doc->createElement('file');
        $issueFile-> setAttribute('desc', 'cover');
        $issueFileNode->appendChild($issueFile)->appendChild($doc->createTextNode('cover'));


//        $secTitle = $doc -> createElement('secTitle');
//        $section->appendChild($secTitle);
//
//        $articleNode=$doc->createElement('article');
//        $artType = $doc->createElement('artType');
//
//        $langPubl = $doc->createElement('langPubl');
//
//        $pages2 = $doc->createElement('pages');
//        $articleNode->appendChild($pages2);
//        $authorsNode = $doc->createElement('authors');
//        $authorNode = $doc->createElement('author');
//        $role = $doc->createElement('role');
//        $correspondent = $doc->createElement('correspondent');
//        $authorCodesNode = $doc->createElement('authorCodes');
//        $researcherid = $doc->createElement('researcherid');
//        $spin = $doc->createElement('spin');
//        $scopusid = $doc->createElement('scopusid');
//        $orcid = $doc->createElement('orcid');
//        $authorCodesNode->appendChild($researcherid);
//        $authorCodesNode->appendChild($spin);
//        $authorCodesNode->appendChild($scopusid);
//        $authorCodesNode->appendChild($orcid);
//        $individInfoNode = $doc->createElement('individInfo');
//        $surname = $doc->createElement('surname');
//        $initials = $doc->createElement('initials');
//        $address = $doc->createElement('address');
//        $town = $doc->createElement('town');
//        $otherInfo = $doc->createElement('otherInfo');
//        $comment = $doc->createElement('comment');
//        $commentDate = $doc->createElement('commentDate');
//        $orgName = $doc->createElement('orgName');
//        $email = $doc->createElement('email');
//        $individInfoNode->appendChild($surname);
//        $individInfoNode->appendChild($initials);
//        $individInfoNode->appendChild($address);
//        $individInfoNode->appendChild($town);
//        $individInfoNode->appendChild($otherInfo);
//        $individInfoNode->appendChild($comment);
//        $individInfoNode->appendChild($commentDate);
//        $individInfoNode->appendChild($orgName);
//        $individInfoNode->appendChild($email);
//        $artTitlesNode=$doc->createElement('artTitles');
//        $artTitle=$doc->createElement('artTitle');
//        $artTitlesNode->appendChild($artTitle);
//        $abstractsNode=$doc->createElement('abstracts');
//        $abstract=$doc->createElement('abstract');
//        $abstractsNode->appendChild($abstract);
//        $text=$doc->createElement('text');
//        $codes=$doc->createElement('codes');
//        $doi=$doc->createElement('doi');
//        $udk=$doc->createElement('udk');
//        $bbk=$doc->createElement('bbk');
//        $jel=$doc->createElement('jel');
//        $msc=$doc->createElement('msc');
//        $pacs=$doc->createElement('pacs');
//        $anycode=$doc->createElement('anycode');
//        $codes->appendChild($doi);
//        $codes->appendChild($udk);
//        $codes->appendChild($bbk);
//        $codes->appendChild($jel);
//        $codes->appendChild($msc);
//        $codes->appendChild($pacs);
//        $codes->appendChild($anycode);
//        $keywords=$doc->createElement('keywords');
//        $kwdGroup=$doc->createElement('kwdGroup');
//        $keyword=$doc->createElement('keyword');
//        $kwdGroup->appendChild($keyword);
//        $keywords->appendChild($kwdGroup);
//        $references=$doc->createElement('references');
//        $reference=$doc->createElement('reference');
//        $references->appendChild($reference);
//        $refInfo=$doc->createElement('refInfo');
//        $reference->appendChild($refInfo);
//        $text2=$doc->createElement('text');
//        $refInfo->appendChild($text2);
//        $elements=$doc->createElement('elements');
//        $element=$doc->createElement('element');
//        $elements->appendChild($element);
//        $br=$doc->createElement('br');
//        $BR=$doc->createElement('BR');
//        $I=$doc->createElement('I');
//        $b=$doc->createElement('b');
//        $B=$doc->createElement('B');
//        $sub=$doc->createElement('sub');
//        $sup=$doc->createElement('sup');
//        $SUB=$doc->createElement('SUB');
//        $SUP=$doc->createElement('SUP');
//        $tex=$doc->createElement('tex');
//        $TEX=$doc->createElement('TEX');
//        $i=$doc->createElement('i');
//        $element->appendChild($br);
//        $element->appendChild($BR);
//        $element->appendChild($I);
//        $element->appendChild($b);
//        $element->appendChild($B);
//        $element->appendChild($sub);
//        $element->appendChild($sup);
//        $element->appendChild($SUB);
//        $element->appendChild($SUP);
//        $element->appendChild($tex);
//        $element->appendChild($TEX);
//        $element->appendChild($i);
//        $br2=$doc->createElement('br');
//        $BR2=$doc->createElement('BR');
//        $I2=$doc->createElement('I');
//        $b2=$doc->createElement('b');
//        $B2=$doc->createElement('B');
//        $sub2=$doc->createElement('sub');
//        $sup2=$doc->createElement('sup');
//        $SUB2=$doc->createElement('SUB');
//        $SUP2=$doc->createElement('SUP');
//        $i2=$doc->createElement('i');
//        $br->appendChild($br2);
//        $br->appendChild($BR2);
//        $br->appendChild($I2);
//        $br->appendChild($b2);
//        $br->appendChild($B2);
//        $br->appendChild($sub2);
//        $br->appendChild($sup2);
//        $br->appendChild($SUB2);
//        $br->appendChild($SUP2);
//        $br->appendChild($i2);
//
//        $BR->appendChild($br2);
//        $BR->appendChild($BR2);
//        $BR->appendChild($I2);
//        $BR->appendChild($b2);
//        $BR->appendChild($B2);
//        $BR->appendChild($sub2);
//        $BR->appendChild($sup2);
//        $BR->appendChild($SUB2);
//        $BR->appendChild($SUP2);
//        $BR->appendChild($i2);
//
//        $I->appendChild($br2);
//        $I->appendChild($BR2);
//        $I->appendChild($I2);
//        $I->appendChild($b2);
//        $I->appendChild($B2);
//        $I->appendChild($sub2);
//        $I->appendChild($sup2);
//        $I->appendChild($SUB2);
//        $I->appendChild($SUP2);
//        $I->appendChild($i2);
//
//        $b->appendChild($br2);
//        $b->appendChild($BR2);
//        $b->appendChild($I2);
//        $b->appendChild($b2);
//        $b->appendChild($B2);
//        $b->appendChild($sub2);
//        $b->appendChild($sup2);
//        $b->appendChild($SUB2);
//        $b->appendChild($SUP2);
//        $b->appendChild($i2);
//
//        $B->appendChild($br2);
//        $B->appendChild($BR2);
//        $B->appendChild($I2);
//        $B->appendChild($b2);
//        $B->appendChild($B2);
//        $B->appendChild($sub2);
//        $B->appendChild($sup2);
//        $B->appendChild($SUB2);
//        $B->appendChild($SUP2);
//        $B->appendChild($i2);
//
//        $sub->appendChild($br2);
//        $sub->appendChild($BR2);
//        $sub->appendChild($I2);
//        $sub->appendChild($b2);
//        $sub->appendChild($B2);
//        $sub->appendChild($sub2);
//        $sub->appendChild($sup2);
//        $sub->appendChild($SUB2);
//        $sub->appendChild($SUP2);
//        $sub->appendChild($i2);
//
//        $sup->appendChild($br2);
//        $sup->appendChild($BR2);
//        $sup->appendChild($I2);
//        $sup->appendChild($b2);
//        $sup->appendChild($B2);
//        $sup->appendChild($sub2);
//        $sup->appendChild($sup2);
//        $sup->appendChild($SUB2);
//        $sup->appendChild($SUP2);
//        $sup->appendChild($i2);
//
//        $SUB->appendChild($br2);
//        $SUB->appendChild($BR2);
//        $SUB->appendChild($I2);
//        $SUB->appendChild($b2);
//        $SUB->appendChild($B2);
//        $SUB->appendChild($sub2);
//        $SUB->appendChild($sup2);
//        $SUB->appendChild($SUB2);
//        $SUB->appendChild($SUP2);
//        $SUB->appendChild($i2);
//
//        $SUP->appendChild($br2);
//        $SUP->appendChild($BR2);
//        $SUP->appendChild($I2);
//        $SUP->appendChild($b2);
//        $SUP->appendChild($B2);
//        $SUP->appendChild($sub2);
//        $SUP->appendChild($sup2);
//        $SUP->appendChild($SUB2);
//        $SUP->appendChild($SUP2);
//        $SUP->appendChild($i2);
//
//        $tex->appendChild($br2);
//        $tex->appendChild($BR2);
//        $tex->appendChild($I2);
//        $tex->appendChild($b2);
//        $tex->appendChild($B2);
//        $tex->appendChild($sub2);
//        $tex->appendChild($sup2);
//        $tex->appendChild($SUB2);
//        $tex->appendChild($SUP2);
//        $tex->appendChild($i2);
//
//        $TEX->appendChild($br2);
//        $TEX->appendChild($BR2);
//        $TEX->appendChild($I2);
//        $TEX->appendChild($b2);
//        $TEX->appendChild($B2);
//        $TEX->appendChild($sub2);
//        $TEX->appendChild($sup2);
//        $TEX->appendChild($SUB2);
//        $TEX->appendChild($SUP2);
//        $TEX->appendChild($i2);
//
//        $i->appendChild($br2);
//        $i->appendChild($BR2);
//        $i->appendChild($I2);
//        $i->appendChild($b2);
//        $i->appendChild($B2);
//        $i->appendChild($sub2);
//        $i->appendChild($sup2);
//        $i->appendChild($SUB2);
//        $i->appendChild($SUP2);
//        $i->appendChild($i2);
//
//        $files=$doc->createElement('files');
//        $file=$doc->createElement('file');
//        $furl=$doc->createElement('furl');
//        $files->appendChild($file);
//        $files->appendChild($furl);
//
//        $rubrics=$doc->createElement('rubrics');
//        $rubric=$doc->createElement('rubric');
//        $rubrics->appendChild($rubric);
//
//        $fundings=$doc->createElement('fundings');
//        $funding=$doc->createElement('funding');
//        $fundings->appendChild($funding);
//
//        $dates=$doc->createElement('dates');
//        $dateReceived=$doc->createElement('dateReceived');
//        $dateAccepted=$doc->createElement('dateAccepted');
//        $dates->appendChild($dateReceived);
//        $dates->appendChild($dateAccepted);
//
//        $files2=$doc->createElement('files');
//        $file2=$doc->createElement('file');
//        $files2->appendChild($file2);
//
//        $refInfo->appendChild($elements);
//        $authorNode->appendChild($role);
//        $authorNode->appendChild($correspondent);
//        $authorNode->appendChild($authorCodesNode);
//        $authorNode->appendChild($individInfoNode);
//
//        $articleNode->appendChild($artType);
//        $articleNode->appendChild($langPubl);
//        $authorsNode->appendChild($authorNode);
//        $articleNode->appendChild($authorsNode);
//        $articleNode->appendChild($artTitlesNode);
//        $articleNode->appendChild($abstractsNode);
//        $articleNode->appendChild($text);
//        $articleNode->appendChild($codes);
//        $articleNode->appendChild($keywords);
//        $articleNode->appendChild($references);
//        $articleNode->appendChild($files);
//        $articleNode->appendChild($rubrics);
//        $articleNode->appendChild($fundings);
//        $articleNode->appendChild($dates);
//        $articles->appendChild($articleNode);
//        $issueNode->appendChild($pages);
//
//        $issueNode->appendChild($files2);
//        $rootNode->appendChild($issueNode);

        /// Ваш код, Олег Владимирович. Закоментил чтобы собрать XML, дальше вы будете в каждый элемент залазить или что-то менять.
//        foreach ($submissions as $submission) {
//            if (!$journal || $journal->getId() != $submission->getContextId()) {
//                $journal = $journalDao->getById($submission->getContextId());
//            }
//
//            $issue = $issueDao->getBySubmissionId($submission->getId(), $journal->getId());
//
//            $articleNode = $doc->createElement('article');
//            $articles->appendChild($articleNode);
//
//            ////////////////////////////
//            $authorListNode = $doc->createElement('authors');
//
//            $publication = $submission->getCurrentPublication();
//            $localeTitle = $publication->getData('title');
//            foreach ((array) $publication->getData('authors') as $author) {
//                $authorListNode->appendChild($this->generateAuthorNodeSiren($doc, $journal, $issue, $submission, $author));
//            }
//            $articleNode->appendChild($authorListNode);
//
//            ///////////////////////
//            $artTitles = $doc->createElement('artTitles');
//            $articleNode->appendChild($artTitles);
//            $artTitleEN = $doc->createElement('artTitle');
//            $artTitleEN-> setAttribute('lang', 'ENG');
//            $artTitleRU = $doc->createElement('artTitle');
//            $artTitleRU-> setAttribute('lang', 'RUS');
//            $artTitles->appendChild($artTitleEN)->appendChild($doc->createTextNode($localeTitle['en_US']));
//            $artTitles->appendChild($artTitleRU)->appendChild($doc->createTextNode($localeTitle['ru_RU']));
//
//            /////////////////////////
//            $abstrTitles = $doc->createElement('abstracts');
//            $articleNode->appendChild($abstrTitles);
//
//            $localeAbstrTitle = $publication->getData('abstract');
//
//            $abstrTitleEN = $doc->createElement('abstract');
//            $abstrTitleEN-> setAttribute('lang', 'ENG');
//            $abstrTitleRU = $doc->createElement('abstract');
//            $abstrTitleRU-> setAttribute('lang', 'RUS');
//            $localeAbstrTitle['en_US'] = PKPString::html2text($localeAbstrTitle['en_US']);
//            $localeAbstrTitle['ru_RU'] = PKPString::html2text($localeAbstrTitle['ru_RU']);
//
//            $abstrTitles->appendChild($abstrTitleEN)->appendChild($doc->createTextNode($localeAbstrTitle['en_US']));
//            $abstrTitles->appendChild($abstrTitleRU)->appendChild($doc->createTextNode($localeAbstrTitle['ru_RU']));
//
//
//        }


        /// хз что за комменты ниже
        //$rootNode->appendChild($doc->createElement('issue')->appendChild($doc->createElement('articles')));
        /*
        foreach ($submissions as $submission) {
            // Fetch associated objects
            if (!$journal || $journal->getId() != $submission->getContextId()) {
                $journal = $journalDao->getById($submission->getContextId());
            }
            $issue = $issueDao->getBySubmissionId($submission->getId(), $journal->getId());

            $articleNode = $doc->createElement('Article');
            $articleNode->appendChild($this->createJournalNode($doc, $journal, $issue, $submission));

            $publication = $submission->getCurrentPublication();

            $locale = $publication->getData('locale');
            if ($locale == 'en_US') {
                $articleNode->appendChild($doc->createElement('ArticleTitle'))->appendChild($doc->createTextNode($publication->getLocalizedTitle($locale)));
            } else {
                $articleNode->appendChild($doc->createElement('VernacularTitle'))->appendChild($doc->createTextNode($publication->getLocalizedTitle($locale)));
            }

            $startPage = $publication->getStartingPage();
            $endPage = $publication->getEndingPage();
            if (isset($startPage) && $startPage !== '') {
                // We have a page range or e-location id
                $articleNode->appendChild($doc->createElement('FirstPage'))->appendChild($doc->createTextNode($startPage));
                $articleNode->appendChild($doc->createElement('LastPage'))->appendChild($doc->createTextNode($endPage));
            }

            if ($doi = $publication->getStoredPubId('doi')) {
                $doiNode = $doc->createElement('ELocationID');
                $doiNode->appendChild($doc->createTextNode($doi));
                $doiNode->setAttribute('EIdType', 'doi');
                $articleNode->appendChild($doiNode);
            }

            $articleNode->appendChild($doc->createElement('Language'))->appendChild($doc->createTextNode(AppLocale::get3LetterFrom2LetterIsoLanguage(substr($locale, 0, 2))));

            $authorListNode = $doc->createElement('AuthorList');
            foreach ((array) $publication->getData('authors') as $author) {
                $authorListNode->appendChild($this->generateAuthorNode($doc, $journal, $issue, $submission, $author));
            }
            $articleNode->appendChild($authorListNode);

            if ($publication->getStoredPubId('publisher-id')) {
                $articleIdListNode = $doc->createElement('ArticleIdList');
                $articleIdNode = $doc->createElement('ArticleId');
                $articleIdNode->appendChild($doc->createTextNode($publication->getStoredPubId('publisher-id')));
                $articleIdNode->setAttribute('IdType', 'pii');
                $articleIdListNode->appendChild($articleIdNode);
                $articleNode->appendChild($articleIdListNode);
            }

            // History
            $historyNode = $doc->createElement('History');
            $historyNode->appendChild($this->generatePubDateDom($doc, $submission->getDateSubmitted(), 'received'));

            $editDecisionDao = DAORegistry::getDAO('EditDecisionDAO');
            $editDecisions = (array) $editDecisionDao->getEditorDecisions($submission->getId());
            do {
                $editorDecision = array_pop($editDecisions);
            } while ($editorDecision && $editorDecision['decision'] != SUBMISSION_EDITOR_DECISION_ACCEPT);

            if ($editorDecision) {
                $historyNode->appendChild($this->generatePubDateDom($doc, $editorDecision['dateDecided'], 'accepted'));
            }
            $articleNode->appendChild($historyNode);

            // FIXME: Revision dates

            if ($abstract = PKPString::html2text($publication->getLocalizedData('abstract', $locale))) {
                $articleNode->appendChild($doc->createElement('Abstract'))->appendChild($doc->createTextNode($abstract));
            }

            $rootNode->appendChild($articleNode);
        }*/

        $doc->appendChild($rootNode);
        return $doc;
    }

	function &process2(&$submissions) {
        // Create the XML document

		$implementation = new DOMImplementation();
		$dtd = $implementation->createDocumentType('journal', '-//NLM//DTD PubMed 2.0//EN', 'http://www.ncbi.nlm.nih.gov/entrez/query/static/PubMed.dtd');
		$doc = $implementation->createDocument('', '', $dtd);
		$doc->preserveWhiteSpace = false;
		$doc->formatOutput = true;

		$issueDao = DAORegistry::getDAO('IssueDAO'); /* @var $issueDao IssueDAO */
		$journalDao = DAORegistry::getDAO('JournalDAO'); /* @var $journalDao JournalDAO */
		$journal = null;

		$rootNode = $doc->createElement('journal');
		foreach ($submissions as $submission) {
			// Fetch associated objects
			if (!$journal || $journal->getId() != $submission->getContextId()) {
				$journal = $journalDao->getById($submission->getContextId());
			}
			$issue = $issueDao->getBySubmissionId($submission->getId(), $journal->getId());

			$articleNode = $doc->createElement('Article');
			$articleNode->appendChild($this->createJournalNode($doc, $journal, $issue, $submission));

			$publication = $submission->getCurrentPublication();

			$locale = $publication->getData('locale');
			if ($locale == 'en_US') {
				$articleNode->appendChild($doc->createElement('ArticleTitle'))->appendChild($doc->createTextNode($publication->getLocalizedTitle($locale)));
			} else {
				$articleNode->appendChild($doc->createElement('VernacularTitle'))->appendChild($doc->createTextNode($publication->getLocalizedTitle($locale)));
			}

			$startPage = $publication->getStartingPage();
			$endPage = $publication->getEndingPage();
			if (isset($startPage) && $startPage !== '') {
				// We have a page range or e-location id
				$articleNode->appendChild($doc->createElement('FirstPage'))->appendChild($doc->createTextNode($startPage));
				$articleNode->appendChild($doc->createElement('LastPage'))->appendChild($doc->createTextNode($endPage));
			}

			if ($doi = $publication->getStoredPubId('doi')) {
				$doiNode = $doc->createElement('ELocationID');
				$doiNode->appendChild($doc->createTextNode($doi));
				$doiNode->setAttribute('EIdType', 'doi');
				$articleNode->appendChild($doiNode);
			}

			$articleNode->appendChild($doc->createElement('Language'))->appendChild($doc->createTextNode(AppLocale::get3LetterFrom2LetterIsoLanguage(substr($locale, 0, 2))));

			$authorListNode = $doc->createElement('AuthorList');
			foreach ((array) $publication->getData('authors') as $author) {
				$authorListNode->appendChild($this->generateAuthorNode($doc, $journal, $issue, $submission, $author));
			}
			$articleNode->appendChild($authorListNode);

			if ($publication->getStoredPubId('publisher-id')) {
				$articleIdListNode = $doc->createElement('ArticleIdList');
				$articleIdNode = $doc->createElement('ArticleId');
				$articleIdNode->appendChild($doc->createTextNode($publication->getStoredPubId('publisher-id')));
				$articleIdNode->setAttribute('IdType', 'pii');
				$articleIdListNode->appendChild($articleIdNode);
				$articleNode->appendChild($articleIdListNode);
			}

			// History
			$historyNode = $doc->createElement('History');
			$historyNode->appendChild($this->generatePubDateDom($doc, $submission->getDateSubmitted(), 'received'));

			$editDecisionDao = DAORegistry::getDAO('EditDecisionDAO'); /* @var $editDecisionDao EditDecisionDAO */
			$editDecisions = (array) $editDecisionDao->getEditorDecisions($submission->getId());
			do {
				$editorDecision = array_pop($editDecisions);
			} while ($editorDecision && $editorDecision['decision'] != SUBMISSION_EDITOR_DECISION_ACCEPT);

			if ($editorDecision) {
				$historyNode->appendChild($this->generatePubDateDom($doc, $editorDecision['dateDecided'], 'accepted'));
			}
			$articleNode->appendChild($historyNode);

			// FIXME: Revision dates

			if ($abstract = PKPString::html2text($publication->getLocalizedData('abstract', $locale))) {
				$articleNode->appendChild($doc->createElement('Abstract'))->appendChild($doc->createTextNode($abstract));
			}

			$rootNode->appendChild($articleNode);
		}
		$doc->appendChild($rootNode);
		return $doc;
	}

	/**
	 * Construct and return a Journal element.
	 * @param $doc DOMDocument
	 * @param $journal Journal
	 * @param $issue Issue
	 * @param $submission Submission
	 */
	function createJournalNode($doc, $journal, $issue, $submission) {
		$journalNode = $doc->createElement('Journal');

		$publisherNameNode = $doc->createElement('PublisherName');
		$publisherNameNode->appendChild($doc->createTextNode($journal->getData('publisherInstitution')));
		$journalNode->appendChild($publisherNameNode);

		$journalTitleNode = $doc->createElement('JournalTitle');
		$journalTitleNode->appendChild($doc->createTextNode($journal->getName($journal->getPrimaryLocale())));
		$journalNode->appendChild($journalTitleNode);

		// check various ISSN fields to create the ISSN tag
		if ($journal->getData('printIssn') != '') $issn = $journal->getData('printIssn');
		elseif ($journal->getData('issn') != '') $issn = $journal->getData('issn');
		elseif ($journal->getData('onlineIssn') != '') $issn = $journal->getData('onlineIssn');
		else $issn = '';
		if ($issn != '') $journalNode->appendChild($doc->createElement('Issn', $issn));

		if ($issue && $issue->getShowVolume()) $journalNode->appendChild($doc->createElement('Volume'))->appendChild($doc->createTextNode($issue->getVolume()));
		if ($issue && $issue->getShowNumber()) $journalNode->appendChild($doc->createElement('Issue'))->appendChild($doc->createTextNode($issue->getNumber()));

		$datePublished = null;
		if ($submission) $datePublished = $submission->getCurrentPublication()->getData('datePublished');
		if (!$datePublished && $issue) $datePublished = $issue->getDatePublished();
		if ($datePublished) {
			$journalNode->appendChild($this->generatePubDateDom($doc, $datePublished, 'epublish'));
		}

		return $journalNode;
	}


    function generateCodesNode($doc, $articleNode, $publication) {
        $codes=$doc->createElement('codes');
        $articleNode->appendChild($codes);

//        $doi=$doc->createElement('doi');
//        $codes->appendChild($doi);

        if ($doi = $publication->getStoredPubId('doi')) {
            $doiNode = $doc->createElement('doi');
            $codes->appendChild($doiNode)->appendChild($doc->createTextNode($doi));
        }


        $udk=$doc->createElement('udk');
        $codes->appendChild($udk);

        $bbk=$doc->createElement('bbk');
        $codes->appendChild($bbk);

        $jel=$doc->createElement('jel');
        $codes->appendChild($jel);

        $msc=$doc->createElement('msc');
        $codes->appendChild($msc);

        $pacs=$doc->createElement('pacs');
        $codes->appendChild($pacs);

        $anycode=$doc->createElement('anycode');
        $codes->appendChild($anycode);
    }

	/**
	 * Generate and return an author node representing the supplied author.
	 * @param $doc DOMDocument
	 * @param $journal Journal
	 * @param $issue Issue
	 * @param $submission Submission
	 * @param $author Author
	 * @return DOMElement
	 */
	function generateAuthorNode($doc, $journal, $issue, $submission, $author) {
		$authorElement = $doc->createElement('Author');

		if (empty($author->getLocalizedFamilyName())) {
			$authorElement->appendChild($node = $doc->createElement('FirstName'));
			$node->setAttribute('EmptyYN', 'Y');
			$authorElement->appendChild($doc->createElement('LastName'))->appendChild($doc->createTextNode(ucfirst($author->getLocalizedGivenName())));
		} else {
			$authorElement->appendChild($doc->createElement('FirstName'))->appendChild($doc->createTextNode(ucfirst($author->getLocalizedGivenName())));
			$authorElement->appendChild($doc->createElement('LastName'))->appendChild($doc->createTextNode(ucfirst($author->getLocalizedFamilyName())));
		}
		$authorElement->appendChild($doc->createElement('Affiliation'))->appendChild($doc->createTextNode($author->getLocalizedAffiliation() . '. ' . $author->getEmail()));

		return $authorElement;
	}

    function generateAuthorNodeSiren($doc, $journal, $issue, $submission, $author, $i) {
        $authorElement = $doc->createElement('author');
        $authorElement->setAttribute('num', $i);

        //$role = $doc->createElement('role');
        //$authorElement->appendChild($role);

        //$correspondent = $doc->createElement('correspondent');
        //$authorElement->appendChild($correspondent);

        $authorCodesNode = $doc->createElement('authorCodes');
        $authorElement->appendChild($authorCodesNode);

        $researcherid = $doc->createElement('researcherid');
        $authorCodesNode->appendChild($researcherid);

        $spin = $doc->createElement('spin');
        $authorCodesNode->appendChild($spin);

        $scopusid = $doc->createElement('scopusid');
        $authorCodesNode->appendChild($scopusid);

        $orcid = $doc->createElement('orcid');
        $authorCodesNode->appendChild($orcid);

        // Данные о публикации -- язык публикации
        $publication = $submission->getCurrentPublication();
        $locale = $publication->getData('locale');

        //$articleNode->appendChild($doc->createElement('Language'))->appendChild($doc->createTextNode(AppLocale::get3LetterFrom2LetterIsoLanguage(substr($locale, 0, 2))));
        $lang = strtoupper(AppLocale::get3LetterFrom2LetterIsoLanguage(substr($locale, 0, 2)));
        $individInfo = $doc->createElement('individInfo');
        $individInfo->setAttribute('lang', $lang);
        $authorElement->appendChild($individInfo);

        if (empty($author->getLocalizedFamilyName())) {
            $individInfo->appendChild($node = $doc->createElement(' initials'));
            $node->setAttribute('EmptyYN', 'Y');
            $individInfo->appendChild($doc->createElement('surname'))->appendChild($doc->createTextNode(ucfirst($author->getLocalizedGivenName())));
        } else {
            $individInfo->appendChild($doc->createElement('initials'))->appendChild($doc->createTextNode(ucfirst($author->getLocalizedGivenName())));
            $individInfo->appendChild($doc->createElement('surname'))->appendChild($doc->createTextNode(ucfirst($author->getLocalizedFamilyName())));
        }

        $address = $doc->createElement('address');
        $individInfo->appendChild($address);

        $town = $doc->createElement('town');
        $individInfo->appendChild($town);

        $otherInfo = $doc->createElement('otherInfo');
        $individInfo->appendChild($otherInfo);

        $comment = $doc->createElement('comment');
        $individInfo->appendChild($comment);

        $commentDate = $doc->createElement('commentDate');
        $individInfo->appendChild($commentDate);

        $individInfo->appendChild($doc->createElement('orgName'))->appendChild($doc->createTextNode($author->getLocalizedAffiliation() . '. ' . $author->getEmail()));

        $email = $doc->createElement('email');
        $individInfo->appendChild($email)->appendChild($doc->createTextNode($author->getEmail()));

        return $authorElement;
    }

    private function createCitationsNode($doc, $publication) {
        $citationDao = DAORegistry::getDAO('CitationDAO');

        $nodeCitations = $doc->createElement('references');
        $submissionCitations = $citationDao->getByPublicationId($publication->getId());

        if ($submissionCitations->getCount() != 0) {
            while ($elementCitation = $submissionCitations->next()) {
                $referenceNode = $doc->createElement('reference');
                $nodeCitations->appendChild($referenceNode);

                $refInfo = $doc->createElement('refInfo');
                $refInfo->setAttribute('lang', 'RUS');
                $referenceNode->appendChild($refInfo);

                $rawCitation = $elementCitation->getRawCitation();
                $refText = $doc->createElement('text', htmlspecialchars($rawCitation, ENT_COMPAT, 'UTF-8'));
                $refInfo->appendChild($refText);
            }

            return $nodeCitations;
        }

        return null;
    }

//    function getFiles($representation) {
//        $submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO'); /* @var $submissionFileDao SubmissionFileDAO */
//        $galleyFiles = array();
//        if ($representation->getFileId()) $galleyFiles = array($submissionFileDao->getLatestRevision($representation->getFileId()));
//        return $galleyFiles;
//    }

	/**
	 * Generate and return a date element per the Siren standard.
	 * @param $doc DOMDocument
	 * @param $pubDate string
	 * @param $pubStatus string
	 * @return DOMElement
	 */
	function generatePubDateDom($doc, $pubDate, $pubStatus) {
        $pubDateNode = $doc->createElement('PubDate');
//        $pubDateNode->setAttribute('PubStatus', $pubStatus);

        $pubDateNode->appendChild($doc->createElement('Year', date('Y', strtotime($pubDate))));
        $pubDateNode->appendChild($doc->createElement('Month', date('m', strtotime($pubDate))));
        $pubDateNode->appendChild($doc->createElement('Day', date('d', strtotime($pubDate))));

		return $pubDateNode;
	}
}


