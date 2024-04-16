function fun(file) {

    const xhttp = new XMLHttpRequest();

    xhttp.onload = function() {
    if (xhttp.status == 200)
        {
	    document.getElementById("startvm_log").value = "siomigadjura" + xhttp.responseText;
	}
    } 
    time=new Date().getTime()    
    xhttp.open("GET", "data/" + file +"?" + time, true);
    xhttp.send(null);
}

//var a;    
//a = setInterval(fun(), 3000);

