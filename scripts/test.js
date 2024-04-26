var logfile=null;

function fun(file) {
    logfile=file;
}


function greet()
{
    const xhttp = new XMLHttpRequest();

    xhttp.onload = function() {
    if (xhttp.status == 200)
        {
	    document.getElementById("startvm_log").value = xhttp.responseText;
	    if (xhttp.responseText.includes("DONE"))
	    {
		//alert("Done");
		document.getElementById("closeButton").style.display = "block";
	    }
	    
	}
    } 
    time=new Date().getTime()    
    xhttp.open("GET", "data/" + logfile +"?" + time, true);
    xhttp.send(null);

}

var a;
a=setInterval(greet, 1000);
