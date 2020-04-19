function refine_dated_stats(stats) {
    let dates_raw = stats.dates;
    let values_raw = stats.values;

    let result = [];
    for(let i = 0; i < dates_raw.length; i++) {
        let arr = dates_raw[i].split("-");
        result.push({
            "t": new Date(arr[2], arr[1], arr[0]),
            "y": values_raw[i]
        });
    }
    result.sort(function(a,b) {
        return a["t"].getTime() - b["t"].getTime();
    });

    return result;
}

$(function() {
    let $mysa_wake = $("#mysa-wake-btn");
    $mysa_wake.click(function(e) {
        e.preventDefault();
        $mysa_wake.html("<i class='fas fa-spinner fa-spin'></i>");
        $.post(mySpires.server + "api/mysa.php").then(function(data){
            $mysa_wake.html("Wake mySa");
            $("#mysa-output").html(data).show();
        });
    });
});

/*
$(function() {
    $.getJSON(mySpires.server + "stats.json").then(function(stats) {
        let api_calls = refine_dated_stats(stats.api_calls);
        let api_plugin_calls = refine_dated_stats(stats.api_plugin_calls);
        let mySa_calls = refine_dated_stats(stats.mySa_calls);

        let ctx = document.getElementById("myChart").getContext('2d');
        let myChart = new Chart(ctx, {
            type: 'line',
            data: {
                datasets: [{
                    label: 'API Calls',
                    fill: false,
                    data: api_calls,
                    borderColor: "#007bff",
                }, {
                    label: 'mySa Calls',
                    fill: false,
                    data: mySa_calls,
                    borderColor: "#28a745",
                }, {
                    label: 'Plugin Calls',
                    fill: false,
                    data: api_plugin_calls,
                    borderColor: "#ffc107",
                }]
            },
            options: {
                scales: {
                    xAxes: [{
                        type: 'time',
                        time: {
                            unit: 'day'
                        }
                    }],
                    yAxes: [{
                        ticks: {
                            beginAtZero: true
                        }
                    }]
                }
            }
        });

    });
});
 */