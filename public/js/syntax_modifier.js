//#region FORMAT MODIFIERS
	//0. InitializeMessage: resets all variables to start message.(at request function)
	//1. Gets the received Chunk.
	//2. escape HTML to prevent injection or mistaken rendering.
	//3. format text for code blocks.
	//4. replace markdown sytaxes for interface rendering

	let isInCodeBlock = false;
	let lastClosingIndex = -1;
	let lastChunk = '';
	let summedText = '';
	function InitializeMessage(){
		isInCodeBlock = false;
		lastClosingIndex = -1;
		lastChunk = '';
		summedText = '';
	}
	function FormatChunk(chunk){
		chunk = escapeHTML(chunk);

		let formattedText = '';
		let prevText = '';
		if(lastClosingIndex != -1){
			prevText = summedText.substring(0 , lastClosingIndex);
			formattedText = summedText.substring(lastClosingIndex);
		}
		else{
			formattedText = summedText;
		}

		if(isInCodeBlock){
			//END OF CODE BLOCK
			if(chunk == '``'){
				isInCodeBlock = false;
			}
			else{
				formattedText = formattedText.replace('</code></pre>', '');
				formattedText += (chunk + '</code></pre>');
			}
		}else{
			// START OF CODE BLOCK
			//Code Syntax Detected
			if(chunk == '```'){
				isInCodeBlock = true;
				formattedText += '<pre><code ignore_Format>';
			}
			else{
				if(chunk.includes('`') && lastChunk == '``'){
					chunk = chunk.replace('`', '');
					lastClosingIndex = summedText.length;
				}  
				//Plain Text
				formattedText += chunk;
			}
		}
		lastChunk = chunk;
		summedText = prevText + formattedText;
		return ReplaceMarkdownSyntax(summedText);
	}


	function FormatChunks(chunk){
		summedText += chunk;
		return FormatSingleResponse(summedText);
	}


	function FormatSingleResponse(raw) {
		// Zuerst Codeblöcke identifizieren und als Platzhalter ersetzen
		let codeBlocks = [];
		let placeholderIndex = 0;
	
		// Verwende ein reguläres Ausdruck, um alle Codeblöcke zu extrahieren und zu speichern
		raw = raw.replace(/```(.*?)```/gs, function(match) {
			codeBlocks.push(match);
			return `@@CODEBLOCK_${placeholderIndex++}@@`;
		});
	
		// Zuerst: Erstelle ein temporäres HTML-Element, um die Mathematik zu rendern
		let tempElement = document.createElement("div");
		tempElement.innerHTML = raw;
		
		// Mathematische Formeln rendern mit KaTeX
		renderMathInElement(tempElement, {
			delimiters: [
				{left: '$$', right: '$$', display: true},
				{left: '$', right: '$', display: false},
				{left: '\\(', right: '\\)', display: false},
				{left: '\\[', right: '\\]', display: true}
			],
			displayMode: true,
			ignoredClasses: ["ignore_Format"],
			throwOnError: true
		});
		
		// Nun ist der Inhalt mit gerenderten Mathematikformeln im `tempElement`
		// Extrahiere den Inhalt als HTML-String
		let mathRenderedHTML = tempElement.innerHTML;
	
		// Jetzt die Code-Blöcke zurück einsetzen
		codeBlocks.forEach((codeBlock, index) => {
			mathRenderedHTML = mathRenderedHTML.replace(`@@CODEBLOCK_${index}@@`, codeBlock);
		});
	
		// Konvertiere den Markdown-Text zu HTML mit marked.js
		let rawHTML = marked.parse(mathRenderedHTML);
	
		// Schließlich: Bereinige das HTML mit DOMPurify
		let sanitizedHTML = DOMPurify.sanitize(rawHTML);
	
		// Gib den bereinigten HTML-Code zurück
		return sanitizedHTML;
	}
	
	


	function escapeHTML(text) {
		return text.replace(/["&'<>]/g, function (match) {
			return {
				'"': '&quot;',
				'&': '&amp;',
				"'": '&#039;',
				'<': '&lt;',
				'>': '&gt;'
			}[match];
		});
	}


	function isJSON(str) {
		try {
			JSON.parse(str);
			return true;
		} catch (e) {
			return false;
		}
	}
	//#endregion