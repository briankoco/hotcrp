function fun(file) {

    const xhttp = new XMLHttpRequest();

    xhttp.onload = function() {
	document.getElementById("startvm_log").value = "siomigadjura" + xhttp.responseText;
    } 
    time=new Date().getTime()    
    xhttp.open("GET", "data/" + file +"?" + time, true);
    xhttp.send(null);
    //a = setInterval(fun(file), 3000);
}

//var a;    
//

