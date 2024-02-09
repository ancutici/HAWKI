visualViewport.addEventListener("resize", update);
visualViewport.addEventListener("scroll", update);
addEventListener("scroll", update);
addEventListener("load", update);

	let abortCtrl = new AbortController();
	let isReceivingData;
	const icon = document.querySelector('#input-send-icon');
	const startIcon = 'M16,12l-4-4l-4,4 M12,16V8';
	const stopIcon = 'M9,9h6v6H9V9z'

	//Load chat by default when the page is loaded.
	window.addEventListener('DOMContentLoaded', (event) => {
		const chatBtn = document.querySelector("#chatMenuButton");
		load(chatBtn ,'chat.htm');
    });

function update(event) {
  event.preventDefault();
  if (!window.visualViewport) {
    return;
  }

  window.scrollTo(0, 0);
  document.querySelector(".wrapper").style.height = window.visualViewport.height + "px";
}

function load(element, filename){
    let messagesElement = document.querySelector(".messages");
    fetch(`views/${filename}`)
      .then((response) => {
        return response.text();
      })
      .then((html) => {
        messagesElement.innerHTML = html;
        return  
      }).then(()=>{
          /*
          let messages = document.querySelectorAll(".message-text");
          messages.forEach(message => {
              message.contentEditable = true;
          })
          */
          if(localStorage.getItem("truth")){
              document.querySelector("#truth")?.remove();
          }
          
          if(filename == "userpost.php"){
              voteHover();
          }
      }
		);
    
    document.querySelector(".menu-item.active")?.classList.remove("active");
    document.querySelector(".menu-item.open")?.classList.remove("open");
    document.querySelector(".submenu-item.active")?.classList.remove("active");
    element.classList.add("active");
    
    element.closest(".submenu")?.previousElementSibling.classList.add("open");
    element.closest(".submenu")?.previousElementSibling.classList.add("active");
    
    document.querySelector(".main").scrollIntoView({ behavior: "smooth", block: "end", inline: "nearest" });
    
    
    
    
}

function submenu(element){
    if(element.classList.contains('active')){
        element.classList.remove("active");
        element.nextElementSibling.classList.remove("active");
    }else{
        document.querySelector(".menu-item.active")?.classList.remove("active");
        document.querySelector(".submenu.active")?.classList.remove("active");
        document.querySelector(".menu-item.open")?.classList.remove("open");
        element.classList.add("active");
        element.nextElementSibling.classList.add("active");
    }
}

function handleKeydown(event){
    if(event.key == "Enter" && !event.shiftKey){
        event.preventDefault();
        request();
    } 
}

function handleKeydownUserPost(event){
    if(event.key == "Enter" && !event.shiftKey){
        event.preventDefault();
        send_userpost();
} 
	}

	function OnSendClick(){
		if(!isReceivingData){
			request();
		} else{
			abortCtrl.abort();
    } 
}

async function request(){
    const messagesElement = document.querySelector(".messages");
    const messageTemplate = document.querySelector('#message');
    const inputField = document.querySelector(".input-field");    
const inputWrapper = document.querySelector(".input-wrapper");

		if(inputField.value.trim() == ""){
			return;
		}

		//handle input-send button.
		isReceivingData = true;
		const icon = document.querySelector('#input-send-icon');
		icon.setAttribute('d', stopIcon)
    
    let message = {};
    message.role = "user";
    //prevent html input to be rendered as html elements.
		message.content = escapeHTML(inputField.value.trim());
    inputField.value = "";
    addMessage(message);
    resize(inputField);
resize(inputWrapper);
    
    document.querySelector('.limitations')?.remove();
    
    const requestObject = {};
    requestObject.model = php_gptModel;
    requestObject.stream = true;
    requestObject.messages = [];
    const messageElements = messagesElement.querySelectorAll(".message");
    messageElements.forEach(messageElement => {
        let messageObject = {};
        messageObject.role = messageElement.dataset.role;
        messageObject.content = messageElement.querySelector(".message-text").textContent;
        requestObject.messages.push(messageObject);
    })
        
    postData('stream-api.php', requestObject)
    .then(stream => processStream(stream))
    .catch(error => console.error('Error:', error));
}

async function postData(url = '', data = {}) {
try{
			abortCtrl = new AbortController();

    const response = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data),
				signal: abortCtrl.signal
    });

    return response.body;

		} catch(error){
			console.log('Fetching Aborted $error');
		}
}

async function processStream(stream) {
		
    // if fetching is aborted before it's complete the stream will be empty.
    // stream should be checked to avoid throwing error.
    if (!stream) {
        console.log('Udefined stream. Early Abortion!');
        isReceivingData = false;
        const icon = document.querySelector('#input-send-icon');
        icon.setAttribute('d', startIcon)

        return;
    }

    const reader = stream.getReader();
    
    const messagesElement = document.querySelector(".messages");
    const messageTemplate = document.querySelector('#message');
    const messageElement = messageTemplate.content.cloneNode(true);
    
    messageElement.querySelector(".message-text").innerHTML = "";
    messageElement.querySelector(".message").dataset.role = "assistant";
    messagesElement.appendChild(messageElement);
    
    const messageText = messageElement.querySelector(".message-text");

    // Throws error if the read operation on the response body stream is aborted while the reader.read() operation is still active.
    // Try Catch block will handle the error.
    try {

			let incompleteSlice = "";
        while (true) {
            const { done, value } = await reader.read();
    
            if (done) {
                console.log('Stream closed.');
                document.querySelector(".message:last-child").querySelector(".message-text").innerHTML = linkify(document.querySelector(".message:last-child").querySelector(".message-text").innerHTML);
                
                isReceivingData = false;
                const icon = document.querySelector('#input-send-icon');
                icon.setAttribute('d', startIcon)
                
                ShowCopyButton();

                break;
            }
    
            //Parsing error from json "Chunks" corrected
				let decodedData = new TextDecoder().decode(value);
decodedData = incompleteSlice + decodedData;


				const delimiter = '\n\n';
				const delimiterPosition = decodedData.lastIndexOf(delimiter);
				if (delimiterPosition > -1) {
					incompleteSlice = decodedData.substring(delimiterPosition + delimiter.length);
					decodedData = decodedData.substring(0,delimiterPosition + delimiter.length);
				} else {
					incompleteSlice = decodedData;
					continue;
				}
 				// if (decodedData.slice(-2) != '\n\n') { console.log("missing newline in end of chunk"); }
                // if (decodedData.slice(-2) != '\n\n') {
                //      incompleteSlice = decodedData;
                //      continue;
                // } else {
                //      incompleteSlice = "";
                // }
				// console.log(decodedData);

				
            let chunks = decodedData.split("data: ");
            chunks.forEach((chunk, index) => {

                if(!isJSON(chunk)){
                    return;
                }
                if(chunk.indexOf('finish_reason":"stop"') > 0) return false;
                if(chunk.indexOf('DONE') > 0) return false;
                if(chunk.indexOf('role') > 0) return false;
                if(chunk.length == 0) return false;
                document.querySelector(".message:last-child").querySelector(".message-text").innerHTML +=  escapeHTML(JSON.parse(chunk)["choices"][0]["delta"].content);
            })
            let messageTextElement = document.querySelector(".message:last-child").querySelector(".message-text");

            // Check if the content has code block
            let innerHTML = document.querySelector(".message:last-child").querySelector(".message-text").innerHTML;
				messageTextElement.innerHTML = ApplyMarkdownFormatting(innerHTML);

            hljs.highlightAll();
            scrollToLast();
        }
    } catch (error) {
        // Check if the error is due to aborting the request
        if (error.name == 'AbortError') {
            console.log('Fetch aborted while reading response body stream.');
        } else {
            console.error('Error:', error);
        }
        isReceivingData = false;
        const icon = document.querySelector('#input-send-icon');
        icon.setAttribute('d', startIcon);
        ShowCopyButton();
    }
}

function isJSON(str) {
    try {
        JSON.parse(str);
        return true;
    } catch (e) {
        return false;
    }
}	

	function ApplyMarkdownFormatting(text) {
		text = text.replace(/```([\s\S]+?)```/g, '<pre><code>$1</code></pre>')
		// Bold
		text = text.replace(/\*\*(.*?)\*\*/g, '<b>$1</b>');
		text = text.replace(/###\s(.*)$/gm, '<b>$1</b>');
		// Links
		text = text.replace(/\[([^\]]+)\]\(([^\)]+)\)/g, '<a href="$2">$1</a>');
		text = text.replace();
		return text;
	}

function escapeHTML(str) {
return str.replace(/&/g, '&amp;')
          .replace(/</g, '&lt;')
          .replace(/>/g, '&gt;')
          .replace(/"/g, '&quot;')
          .replace(/'/g, '&#039;');
}


function addMessage(message){
    const messagesElement = document.querySelector(".messages");
    const messageTemplate = document.querySelector('#message');
    const inputField = document.querySelector(".input-field");
    const messageElement = messageTemplate.content.cloneNode(true);
    
    messageElement.querySelector(".message-text").innerHTML = message.content;
    messageElement.querySelector(".message").dataset.role = message.role;
    
    if(message.role == "assistant"){
        messageElement.querySelector(".message-icon").textContent = "AI";
    }else{
        messageElement.querySelector(".message-icon").textContent = '<?= $_SESSION['username'] ?>';
        messageElement.querySelector(".message").classList.add("me");
    }
    
    messagesElement.appendChild(messageElement);
    
    scrollToLast(true);
    return messageElement;
}

//#region Scrolling Controls
	//scrolls to the end of the panel.
	//if new message is send, it forces the panel to scroll down.
	//if the current message is continuing to expand force expand is false.
	//(if the user is trying to read the upper parts it wont jump back down.)
	let isScrolling = false;
	function scrollToLast(forceScroll){

		const msgsPanel = document.querySelector('.messages');
		const documentHeight = msgsPanel.scrollHeight;
		const currentScroll = msgsPanel.scrollTop + msgsPanel.clientHeight;
		if (!isScrolling && (forceScroll || documentHeight - currentScroll < 150)) {
    const messagesElement = document.querySelector(".messages");

    messagesElement.scrollTo({
      top: messagesElement.scrollHeight,
      left: 0,
      behavior: "smooth",
    });
}
}

	document.querySelector('.messages').addEventListener('scroll', function() {
		isScrolling = true;
	});
	document.querySelector('.messages').addEventListener('scroll', function() {
		setTimeout(function() {
			isScrolling = false;
		}, 700); // Adjust the threshold
	});
	//#endregion


function resize(element) {
    element.style.height = 'auto';
    element.style.height = element.scrollHeight + "px";
    element.scrollTop = element.scrollHeight;
    element.scrollTo(element.scrollTop, (element.scrollTop + element.scrollHeight));
}

function copyToInput(selector) {
    const originalText = document.querySelector(selector).textContent;
		const cleanedText = originalText.split('\n')  // Split text by new lines
									.map(line => line.trim())  // Remove leading and trailing spaces from each line
									.filter(line => line !== '')  // Filter out empty lines
									.join(' ');  // Join lines back together with a single space

		document.querySelector(".input-field").value = cleanedText;
    resize(document.querySelector(".input-field"));
}

if(localStorage.getItem("data-protection")){
    document.querySelector("#data-protection").remove();
}

if(localStorage.getItem("gpt4")){
    document.querySelector("#gpt4").remove();
}

function modalClick(element){
    localStorage.setItem(element.id, "true")
    element.remove();
}


async function send_userpost(){
    const messagesElement = document.querySelector(".messages");
    const messageTemplate = document.querySelector('#message');
    const inputField = document.querySelector(".userpost-field");
    
    let message = {};
    message.role = php_username;
    message.content = inputField.value.trim();
    
    fetch('userpost.php', {
        method: 'POST',
        body: JSON.stringify(message),
    })
    .then(response => response.json())
    .then(data => {
        console.log(data)
        load(document.querySelector("#feedback"), 'userpost.php');
        inputField.value = "";
    })
    .catch(error => console.error(error));
}

async function upvote(element){
    if(localStorage.getItem(element.dataset.id)){
        return;
    }
    localStorage.setItem(element.dataset.id, "true");
    fetch('upvote.php', {
        method: 'POST',
        body: element.dataset.id,
    })
    .then(response => response.text())
    .then(data => {
        console.log(data)
        element.querySelector("span").textContent = parseInt(element.querySelector("span").textContent) + 1;
    })
    .catch(error => console.error(error));
    
    voteHover();
}

async function downvote(element){
    if(localStorage.getItem(element.dataset.id)){
        return;
    }
    localStorage.setItem(element.dataset.id, "true");
    fetch('downvote.php', {
        method: 'POST',
        body: element.dataset.id,
    })
    .then(response => response.text())
    .then(data => {
        console.log(data)
        element.querySelector("span").textContent = parseInt(element.querySelector("span").textContent) + 1;
    })
    .catch(error => console.error(error));
    
    voteHover();
}

async function voteHover(){
    let messages = document.querySelectorAll(".message");
      
      messages.forEach((message)=>{
          let voteButtons = message.querySelectorAll(".vote")
          
          voteButtons.forEach((voteButton)=>{
              if(localStorage.getItem(voteButton.dataset.id)){
                  voteButton.classList.remove("vote-hover");
              }else{
                  voteButton.classList.add("vote-hover");
              }
          })
                })
}

document.querySelectorAll('details').forEach((D,_,A)=>{
  D.ontoggle =_=>{ if(D.open) A.forEach(d =>{ if(d!=D) d.open=false })}
})

function linkify(htmlString) {
  const urlRegex = /((https?:\/\/|www\.)[-a-zA-Z0-9@:%._\+~#=]{2,256}\.[a-z]{2,6}\b([-a-zA-Z0-9@:%_\+.~#?&//=]*))/g;
  return htmlString.replace(urlRegex, '<a href="$1" target="_blank">$1</a>');
}


//#region Copy Button

	function ShowCopyButton() {
		const copyPanel = document.querySelector(".message:last-child").querySelector(".message-copypanel");
		if (copyPanel !== null) {
			copyPanel.style.display = "flex";
			const copyButton = copyPanel.querySelector(".message-copyButton");
			copyButton.dataset.clicked = "false"; // Initialize the clicked state for this button
			AddEventListenersToCopyButton(copyButton);
		}
	}

	function AddEventListenersToCopyButton(TargetButton){
		
		TargetButton.addEventListener("mouseenter", function() {
			setTimeout(function() {
				TargetButton.querySelector(".tooltiptext").classList.add("active");
			}, 1000);
		});

		TargetButton.addEventListener("mouseleave", function () {
			if (TargetButton.dataset.clicked !== "true") { // Check the clicked state of this button
				TargetButton.querySelector(".tooltiptext").classList.remove("active");
			}
    	});

		TargetButton.addEventListener("mousedown", function () {
			TargetButton.dataset.clicked = "true"; // Set the clicked state of this button to true
			CopyContentToClipboard(TargetButton);
		});

		TargetButton.addEventListener("mouseup", function() {
			CopyBtnRelease(TargetButton);
		});
	}

	function CopyContentToClipboard(target) {
		const msgTxt = target.parentElement.previousElementSibling.textContent;
		const trimmedMsg = msgTxt.trim();
		navigator.clipboard.writeText(trimmedMsg);

		target.style.fill = "rgba(35, 48, 176, 1)";
		target.style.scale = "1.1";

		target.querySelector(".tooltiptext").classList.add("active");
		target.querySelector(".tooltiptext").innerHTML = "Kopiert!"
	}

	function CopyBtnRelease(target) {
		target.style.scale = "1";

		setTimeout(function () {
			target.style.fill = "rgba(35, 48, 176, .5)";

			target.querySelector(".tooltiptext").classList.remove("active");
			target.querySelector(".tooltiptext").innerHTML = "Kopieren"
			target.dataset.clicked = "false"; // Reset the clicked state of this button
		}, 2000);
	}
//#endregion
</script>