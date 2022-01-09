<?php
	$matches = [
		[
			'question' => 'Abner',
			'answer' => 'Commander of Israel\'s army, murdered',
			'reference' => ''
		],
		[
			'question' => 'Asa',
			'answer' => 'His heart was loyal to the Lord all his days.',
			'reference' => ''
		],
		[
			'question' => 'Ben-Geber',
			'answer' => 'Governor (6) in Ramoth Gilead',
			'reference' => ''
		],
		[
			'question' => 'Hezron',
			'answer' => 'Grandson of Tamar',
			'reference' => ''
		],
		[
			'question' => 'Tamar',
			'answer' => 'Perez\'s mom',
			'reference' => ''
		],
	];
?>

<div class="row">
	<div class="col s6">
		<h2>Quiz Bank</h2>
		<ul class="browser-default quiz-bank" id="quiz-bank">

		</ul>
	</div>
	<div class="col s6">
		<h2>Answers</h2>
		<ul id="answers">
		</ul>
	</div>
</div>

<button class="btn waves-effect waves-light submit">Check Answers</button>
<button class="btn waves-effect waves-light submit blue">Start over!</button>

<div class="hidden" id="quiz-bank-item-template">
	<span class="item-text" data-id=""></span>
</div>
<div class="hidden" id="answer-item-template">
	<div class="row" data-id="">
		<div class="col s6" style="background-color:lightgreen; height: 50px">
			<ul class="answer-drop" style="background-color:yellow; height: 50px">

			</ul>
		</div>
		<div class="col s6 answer-text">
			
		</div>
	</div>
</div>

<script type="text/javascript">
	var matches = <?= json_encode($matches) ?>;
	var quizBankUL = document.getElementById('quiz-bank');
	var answersUL = document.getElementById('answers');
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
		quizBankUL.appendChild(li);
		// setup answer 
		var answerItem = answerItemTemplate.cloneNode(true);
		var li = document.createElement('li');
		li.appendChild(answerItem);
		var answerTextSpans = li.getElementsByClassName('answer-text');
		if (answerTextSpans.length > 0) {
			answerTextSpans[0].innerText = item['answer'];
		}
		answersUL.appendChild(li);
	});
	var sortable = Sortable.create(quizBankUL, {
		group: {
			name: 'shared'
		},
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
		});
	};
</script>