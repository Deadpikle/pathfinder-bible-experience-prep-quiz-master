<?php
	$matches = [
		[
			'id' => 1,
			'question' => 'Abner',
			'answer' => 'Commander of Israel\'s army, murdered',
			'reference' => ''
		],
		[
			'id' => 2,
			'question' => 'Asa',
			'answer' => 'His heart was loyal to the Lord all his days.',
			'reference' => ''
		],
		[
			'id' => 3,
			'question' => 'Ben-Geber',
			'answer' => 'Governor (6) in Ramoth Gilead',
			'reference' => ''
		],
		[
			'id' => 4,
			'question' => 'Hezron',
			'answer' => 'Grandson of Tamar',
			'reference' => ''
		],
		[
			'id' => 5,
			'question' => 'Tamar',
			'answer' => 'Perez\'s mom',
			'reference' => ''
		],
	];
?>

<h1>Matching Quiz!</h1>
<p>Drag items from the question bank on the left to the corresponding matches/answers on the right. Then, to check your answers, click the "Check Answers" button at the bottom. If a match is correct, you will see that item turn green and a <i class="fas fa-check-circle"></i> icon. If the match is not correct, you will see that item turn red and a <i class="fas fa-exclamation-triangle"></i> icon.</p>

<div class="row">
	<div class="col s6 m4 l4" id="quiz-bank-container">
		<h2>Quiz Bank</h2>
		<ul class="browser-default quiz-bank" id="quiz-bank">

		</ul>
	</div>
	<div class="col s6 m8 l6" id="answers-container">
		<h2>Answers</h2>
		<ul id="answers">
		</ul>
	</div>
</div>

<button class="btn waves-effect waves-light submit" id="check-answer-button">Check Answers</button>
<button class="btn waves-effect waves-light submit blue">Start over!</button>

<div class="hidden" id="quiz-bank-item-template">
	<p class="quiz-bank-item"><i class="fas fa-arrows-alt drag-handle"></i><span class="item-text"></span></p>
</div>
<div class="hidden" id="answer-item-template">
	<div class="row answer-item valign-wrapper">
		<div class="col s5 m4">
			<ul class="answer-drop">

			</ul>
		</div>
		<div class="col s7 m8">
			<p class="answer-text-container">
				<span class="answer-text"></span>
				<i class="fas fa-check-circle hidden correct-answer-icon"></i>
				<i class="fas fa-exclamation-triangle hidden wrong-answer-icon"></i>
			</p>
			
		</div>
	</div>
</div>

<script type="text/javascript">
	// https://stackoverflow.com/a/2450976/3938401
	function shuffle(array) {
		let currentIndex = array.length,  randomIndex;
		// While there remain elements to shuffle...
		while (currentIndex != 0) {
			// Pick a remaining element...
			randomIndex = Math.floor(Math.random() * currentIndex);
			currentIndex--;

			// And swap it with the current element.
			[array[currentIndex], array[randomIndex]] = [
			array[randomIndex], array[currentIndex]];
		}
		return array;
	}

	var matches = <?= json_encode($matches) ?>;
	var quizBankUL = document.getElementById('quiz-bank');
	var answersUL = document.getElementById('answers');
	var checkAnswerButton = document.getElementById('check-answer-button');
	var quizBankItemTemplate = document.getElementById('quiz-bank-item-template').firstElementChild;
	var answerItemTemplate = document.getElementById('answer-item-template').firstElementChild;
	matches.forEach(function(item) {
		// setup quiz option
		var quizBankItem = quizBankItemTemplate.cloneNode(true);
		var li = document.createElement('li');
		li.appendChild(quizBankItem);
		var itemTextSpans = li.getElementsByClassName('item-text');
		if (itemTextSpans.length > 0) {
			itemTextSpans[0].innerText = item['question'];
		}
		li.classList.add('quiz-list-item');
		li.setAttribute('data-id', item['id']);
		quizBankUL.appendChild(li);
	});
	// shuffle answers and display them
	var shuffledMatches = shuffle(matches);
	shuffledMatches.forEach(function(item) {
		// setup answer 
		var answerItem = answerItemTemplate.cloneNode(true);
		var li = document.createElement('li');
		li.appendChild(answerItem);
		var answerTextSpans = li.getElementsByClassName('answer-text');
		if (answerTextSpans.length > 0) {
			answerTextSpans[0].innerText = item['answer'];
		}
		li.classList.add('answer-list-item');
		li.setAttribute('data-id', item['id']);
		answersUL.appendChild(li);
	});
	// set up sortables
	var sortable = Sortable.create(quizBankUL, {
		group: {
			name: 'shared'
		},
		handle: '.drag-handle',
		easing: 'cubic-bezier(1, 0, 0, 1)',
		ghostClass: 'sortable-drop-placeholder',
		sort: false // no sorting within list
	});
	var answerDrops = document.getElementsByClassName('answer-drop');
	// can't use forEach since getElementsByClassName returns HTMLCollection
	for (var i = 0; i < answerDrops.length; i++) {
		var item = answerDrops[i];
		var sortable = Sortable.create(item, {
			group: {
				name: 'shared',
				put: function (to) {
					return to.el.children.length < 1; // max 1 item in list
				}
			},
			handle: '.drag-handle',
			easing: 'cubic-bezier(1, 0, 0, 1)',
			ghostClass: 'sortable-drop-placeholder',
			swap: false, // Enable swap mode
			swapClass: "sortable-swap-highlight" // Class name for swap item (if swap mode is enabled)
		});
	};

	function checkAnswers() {
		// check answers by looking at the answer <li> vs the quiz bank <li>.
		// if the data-id attribute matches, then the answer is correct :)
		var answerListItems = answersUL.getElementsByClassName('answer-list-item');
		for (var i = 0; i < answerListItems.length; i++) {
			var answerListItem = answerListItems[i];
			var answerDataID = answerListItem.getAttribute('data-id');
			var answerItemContainers = answerListItem.getElementsByClassName('answer-item');
			if (answerItemContainers.length > 0) {
				// clear any old CSS answers
				answerItemContainers[0].classList.remove('correct-quiz-answer');
				answerItemContainers[0].querySelector('.correct-answer-icon').classList.add('hidden');
				answerItemContainers[0].classList.remove('wrong-quiz-answer');
				answerItemContainers[0].querySelector('.wrong-answer-icon').classList.add('hidden');
				// check if user has matched an item at all
				var quizBankListItems = answerListItem.getElementsByClassName('quiz-list-item');
				if (quizBankListItems.length === 0) {
					// if not matched at all, it's wrong by default
					answerItemContainers[0].classList.add('wrong-quiz-answer');
					answerItemContainers[0].querySelector('.wrong-answer-icon').classList.remove('hidden');
				} else {
					// see if answer right
					var quizBankListItem = quizBankListItems[0];
					var quizBankDataID = quizBankListItem.getAttribute('data-id');
					if (quizBankDataID === answerDataID) {
						// answer is right!
						answerItemContainers[0].classList.add('correct-quiz-answer');
						answerItemContainers[0].querySelector('.correct-answer-icon').classList.remove('hidden');
					} else {
						answerItemContainers[0].classList.add('wrong-quiz-answer');
						answerItemContainers[0].querySelector('.wrong-answer-icon').classList.remove('hidden');
					}
				}
			}
		}
	}

	checkAnswerButton.addEventListener('click', function() {
		checkAnswers();
	});

</script>