@php
    $generatedAt = now()->format('d/m/Y H:i');
    $projects = $data['projects'] ?? [];
    $phases = $data['phases'] ?? [];
    $tasks = $data['tasks'] ?? [];
    $kpiMonthly = $data['kpi']['monthly'] ?? [];
    $kpiQuarterly = $data['kpi']['quarterly'] ?? [];
    $topPerformers = collect($data['top_performers'] ?? []);
    $recentTasks = collect($data['recent_tasks'] ?? []);
    $approvalTasks = collect($data['approval_tasks'] ?? []);
@endphp

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <style>
        @font-face {
    font-family: 'NotoSans';
    src: url('data:font/truetype;charset=utf-8;base64,AAEAAAANAIAAAwBQR0RFRujT1GcABRqsAAAFGkdQT1Mz0FO0AAUfyAABFUxHU1VCcc8vegAGNRQAAC7uT1MvMo4VhgkAAAFYAAAAYGNtYXC7LmevAAA+aAAAMdhnbHlm+6VlJwAArPQAA7+WaGVhZCZZtukAAADcAAAANmhoZWEMsxa0AAABFAAAACRobXR4aR+ORQAAAbgAADywbG9jYR40iXMAAHBAAAA8tG1heHAPTwGAAAABOAAAACBuYW1loYDFPQAEbIwAAAXmcG9zdHTCuXUABHJ0AACoNwABAAAAAgNUaOyPsV8PPPUAAwPoAAAAAN2A0+cAAAAA4T2cJP2T/nsK8AQrAAAABgACAAEAAAAAAAEAAAQt/tsAAAsY/ZP9lArwAAEAAAAAAAAAAAAAAAAAAA8sAAEAAA8sARgAGABmAAYAAQAAAAAAAAAAAAAAAAAEAAEABAJBAZAABQAAAooCWAAAAEsCigJYAAABXgAyAUIAAAILBQIEBQQCAgTgAAL/QAAgHwgAACkAEAAAR09PRwDAAAD//wQt/tsAAARkAYsAAAGfAAAAAAIYAsoAAAAgAAQCWABeAAAAAAEEAAABBAAAAQ0ASAGYAEEChgAZAjwAPgM/ADEC3AA1AOEAQQEsACgBLAAeAicAKQI8ADIBDAApAUIAKAEMAEgBdAAKAjwAMQI8AFkCPAAwAjwALQI8ABUCPAA/AjwANwI8ACwCPAAxAjwAMgEMAEgBDAAfAjwAMgI8ADgCPAAyAbIADAODADoCfwAAAooAYQJ4AD0C2gBhAiwAYQIHAGEC2AA9AuUAYQFTACgBEf+yAmsAYQIMAGEDiwBhAvgAYQMNAD0CXQBhAw0APQJuAGECJQAzAiwACgLbAFoCWAAAA6IADAJKAAQCNgAAAjwAJgFJAFABdAAKAUkAGQI8ACYBvP/+ARkAKAIxAC4CZwBVAeAANwJnADcCNAA3AVgADwJnADcCagBVAQIATgEC/8kCFgBVAQIAVQOnAFUCagBVAl0ANwJnAFUCZwA3AZ0AVQHfADMBaQAQAmoATwH8AAADEgALAhEAEgH+AAEB1gAnAXwAHAInAO8BfAAgAjwAMgEEAAABDQBIAjwAWwI8ACACPAA7AjwADgInAO8CAQA7AkQAlQNAADEBZQAgAf0AKAI8ADIBQgAoA0AAMQH0//0BrAA3AjwAMgFeABgBXgARARkAKAJvAFUCjwA3AQwASADhAA4BXgAlAXgAIAH9ACcC6QAiAwMAFgMNAA8BsgAYAn8AAAJ/AAACfwAAAn8AAAJ/AAACfwAAA3H//wJ4AD0CLABhAiwAYQIsAGECLABhAVMAKAFTACgBUwABAVMAHgLaAB4C+ABhAw0APQMNAD0DDQA9Aw0APQMNAD0CPABAAw0APQLbAFoC2wBaAtsAWgLbAFoCNgAAAl0AYQJ3AFUCMQAuAjEALgIxAC4CMQAuAjEALgIxAC4DYAAuAeAANwI0ADcCNAA3AjQANwI0ADcBAv//AQIATAEC/9gBAv/1Al0ANwJqAFUCXQA3Al0ANwJdADcCXQA3Al0ANwI8ADICXQA3AmoATwJqAE8CagBPAmoATwH+AAECZwBVAf4AAQJ/AAACMQAuAn8AAAIxAC4CfwAAAjEALgJ4AD0B4AA3AngAPQHgADcCeAA9AeAANwJ4AD0B4AA3AtoAYQJnADcC2gAeAmkANwIsAGECNAA3AiwAYQI0ADcCLABhAjQANwIsAGECNAA3AiwAYQI0ADcC2AA9AmcANwLYAD0CZwA3AtgAPQJnADcC2AA9AmcANwLlAGECav/ZAuUAAAJqAAkBU//zAQL/ygFTABUBAv/sAVMADgEC/+UBUwAoAQIAGwFTACgCZAAoAgQATgER/7IBAv/JAmsAYQIWAFUCFgBVAgwAVwECAEwCDABhAQIAQQIMAGEBAgBVAgwAYQEM
    format('truetype');
    font-weight: 400;
    font-style: normal;
}
        @font-face {
    font-family: 'NotoSans';
    src: url('data:font/truetype;charset=utf-8;base64,AAEAAAANAIAAAwBQR0RFRulY1JQABRyEAAAFGkdQT1MyugQ3AAUhoAABFJhHU1VC2S4PXQAGefAAACh6T1MvMo9HiRwAAAFYAAAAYGNtYXC7LmevAAA+aAAAMdhnbHlmqrJbsgAArPQAA8GIaGVhZCZDt0gAAADcAAAANmhoZWEMnBawAAABFAAAACRobXR4qFNIVQAAAbgAADywbG9jYR43xcIAAHBAAAA8tG1heHAPTwF/AAABOAAAACBuYW1lnSrC4QAEbnwAAAXOcG9zdHTCuXUABHRMAACoNwABAAAAAgNUlClImV8PPPUAAwPoAAAAAN2A0+cAAAAA4T2cgf18/nsKzwQ8AAIABgACAAEAAAAAAAEAAAQt/tsAAArw/Wf9ZwrPA+gA1QAAAAAAAAAAAAAAAA85AAEAAA85AS0AGABxAAYAAQAAAAAAAAAAAAAAAAAEAAEABAImAZAABQAAAooCWP/wAEsCigJYAEoBXgAyAUIAAAILBQIEBQQJAgTgAAL/QAAgHwgAACkAEAAAR09PRwCgAAD//wQt/tsAAARkAYsAAAGfAAAAAAIiAsoAAAAgAAQCWABeAAAAAAEEAAABBAAAAR4AOQHYAEEChgAWAjwAKwOFAB8C7gAoAQoAQQFTACgBUwAeAiEAHwI8ACsBHQAfAUIAHgEdADkBnQAHAjwAJAI8ADsCPAAmAjwAJgI8ABECPAAxAjwAIwI8ABsCPAAjAjwAIAEdADkBHQAfAjwAKwI8ACsCPAArAd0AAwOBADICsgAAAqAAWgJ9ADoC5ABaAjAAWgIlAFoC1AA6Av0AWgGFACABS/+2ApgAWgI1AFoDrwBaAy0AWgMcADoCdABaAxwAOgKUAFoCJwAuAkMAFAL0AFUCigAAA8cAAAKbAAACcAAAAkMAGAFLAEYBnQAGAUsAGQI8ABcBm//+AWoAKAJcACoCeQBOAgIALQJ5AC0CTwAtAYMAFAJ5AC0CkQBOATEASAEx/8ACbABOATEATgPWAE4CkQBOAmsALQJ5AE4CeQAtAcYATgHxAC0BsgAXApEASwI5AAADWAAKAkIABQI5AAAB6AAbAYoADwInAN4BigAoAjwAKwEEAAABHgA5AjwARgI8ACgCPAA3AjwAAwInAN4B5gA0Al8AiANAADEBfwAXAmcAKAI8ACsBQgAeA0AAMQH0//0BrAAnAjwAKwF7ABcBewAdAWoAKAKUAE4CjwA3AR0AOQDN/+4BewAtAYQAHAJnACgDPgAWA2oAFgNNACwB3QAbArIAAAKyAAACsgAAArIAAAKyAAACsgAAA7gAAAJ9ADoCMABaAjAAWgIwAFkCMABaAYUADgGFACABhf/vAYUAGwLkABcDLQBaAxwAOgMcADoDHAA6AxwAOgMcADoCPAA/AxwAOgL0AFUC9ABVAvQAVQL0AFUCcAAAAnQAWgLHAE4CXAAqAlwAKgJcACoCXAAqAlwAKgJcACoDlQAqAgIALQJPAC0CTwAtAk8ALQJPAC0BMf/kATEATgEx/8UBMf/xAmsALQKRAE4CawAtAmsALQJrAC0CawAtAmsALQI8ACsCawAtApEASwKRAEsCkQBLApEASwI5AAACeQBOAjkAAAKyAAACXAAqArIAAAJcACoCsgAAAlwAKgJ9ADoCAgAtAn0AOgICAC0CfQA6AgIALQJ9ADoCAgAtAuQAWgJ5AC0C5AAXAokALQIwAFoCTwAtAjAAWgJPAC0CMABaAk8ALQIwAFoCTwAtAjAAWQJPAC0C1AA6AnkALQLUADoCeQAtAtQAOgJ5AC0C1AA6AnkALQL9AFoCkf/HAv0AAAKRAAIBhf/5ATH/zwGFAB0BMf/zAYUAAwEx/9kBhQAgATEALQGFACAC0AAgAmIASAFL/7YBMf/AApgAWgJsAE4CbABOAjUAWgExAE4CNQBaATEARQI1AFoBMQBOAjUAWgFq
    format('truetype');
    font-weight: 700;
    font-style: normal;
}
        @font-face {
    font-family: 'NotoSans';
    src: url('data:font/truetype;charset=utf-8;base64,AAEAAAANAIAAAwBQR0RFRv4n4o4ABVoAAAAFdkdQT1Ntg9P2AAVfeAABGyZHU1VC2S4PXQAGegAAACh6T1MvMo9IikQAAAFYAAAAYGNtYXAgAbDVAAA+nAAAK5RnbHlm8mBEVwAApxgABAMhaGVhZCYNtIwAAADcAAAANmhoZWENORqHAAABFAAAACRobXR4Y7LPWQAAAbgAADzkbG9jYR+qqdEAAGowAAA86G1heHAPXAGXAAABOAAAACBuYW1ljhSeSAAEqjwAAAbEcG9zdOs5rhsABLEAAACo/QABAAAAAgNUg+WEbl8PPPUAAwPoAAAAAN2AzKIAAAAA4T2hDP1Y/nsK3AQrAAMABgACAAEAAAAAAAEAAAQt/tsAAArw/Vj9mwrcA+gA1QAAAAAAAAAAAAAAAA85AAEAAA85AS0AGABoAAYAAQAAAAAAAAAAAAAAAAAEAAEABAJQArwABQAAAooCWP/wAEsCigJYAEoBXgAyAUgAAAILCAIEBQQJAgTgAAL/QAAgHwgAACkAEAAAR09PRwGhAAD//wQt/tsAAARkAYsAAAGfAAAAAAIiAsoAAAAgAAQCWABeAAAAAAEEAAABBAAAAR4ADAHFAGIChgAUAicAGQNYADgCxAAhAP8AYgFTACQBU/+4AiEAVAI8ADUBHf/OAUIAFAEdAAwBpf/UAicAIAInAFkCJ//oAicABwIn//QCJwANAicAKwInACYCJwAbAicAKgEdAAwBHf/OAjwANQI8ADUCPAA1AcsATQNYAC0CdP/EAmwAGgJkADwCpQAaAh4AGgITABoCsgA8ArwAGgF//+MBS/9jAmUAGgIGABoDcAAaAvMAGgLaADwCYgAaAtoAPAJhABoCEgAUAhMAUgKzAEUCTgBaA34AWgJe/8cCNABbAhj/4gFL/+4BpQBsAUv/vQInABEBkP+lAUIAkQJSACwCUwASAeMALAJSACwCLQAsAXX/lQJSAA0CXAASASkAEgEp/38COAASASkAEgOJABICXAASAkkALAJT/98CUgAsAaUAEgHZAAsBmgAuAlwANgIAADIDFAA9AhP/zwIH/7sBx//pAWP//AInAN4BY//PAjwANQEEAAABHv/cAicAUAIn//oCJwA4AicAKwInAN4B5gAKAioAywNAADEBeQBKAjIAIwI8ADUBQgAUA0AAMQH0AFcBrAAnAjwANQF7ADgBewBIAUIAcQKA/98CjwBIAR0AQADN/5sBewBbAXAAUwIyAAADQgBHA3sARwNfAC8By//kAnT/xAJ0/8QCdP/EAnT/xAJ0/8QCdP/EA3//xAJkADwCHgAaAh4AGgIeABoCHgAaAX//4wF//+MBf//jAX//4wKlABIC8wAaAtoAPALaADwC2gA8AtoAPALaADwCPABJAtoAMQKzAEUCswBFArMARQKzAEUCNABbAl4AGgKT/4ECUgAsAlIALAJSACwCUgAsAlIALAJSACwDaAAsAeMALAItACwCLQAsAi0ALAItACwBKQASASkAEgEpAA4BKQASAkEAIwJcABICSQAsAkkALAJJACwCSQAsAkkALAI8ADUCSQAVAlwANgJcADYCXAA2AlwANgIH/7sCU//fAgf/uwJ0/8QCUgAsAnT/xAJSACwCdP/EAlIALAJkADwB4wAsAmQAPAHjACwCZAA8AeMALAJkADwB4wAsAqUAGgKjACwCpQASAlIALAIeABoCLQAsAh4AGgItACwCHgAaAi0ALAIeABoCLQAsAh4AGgItACwCsgA8AlIADQKyADwCUgANArIAPAJSAA0CsgA8AlIADQK8ABoCXAASArwAGgJcABIBf//jASkAEgF//+MBKQASAX//4wEpABIBf//jASn/3AF//+MCyv/jAlIAEgFL/2MBKf+AAmUAGgI4ABICOAASAgYAGgEpABICBgAaASn/2gIGABoBdwASAgYAGgGy
    format('truetype');
    font-weight: 700;
    font-style: italic;
}

        *, *::before, *::after {
            box-sizing: border-box !important;
        }

        html, body {
            font-family: 'NotoSans', sans-serif;
            font-size: 11px;
            color: #0f172a;
            margin: 0;
            padding: 0;
        }

        table, th, td, p, div, span {
            font-family: 'NotoSans', sans-serif;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            border: 4px solid #ff0000 !important;
            background: #fff;
        }

        th, td {
            border: 3px solid #ff0000 !important;
            border-left: 3px solid #ff0000 !important;
            border-right: 3px solid #ff0000 !important;
            border-top: 3px solid #ff0000 !important;
            border-bottom: 3px solid #ff0000 !important;
            padding: 8px;
            vertical-align: middle;
            background: #fff;
        }
        @font-face {
    font-family: 'NotoSans';
    src: url('data:font/truetype;charset=utf-8;base64,AAEAAAANAIAAAwBQR0RFRulY1JQABRyEAAAFGkdQT1MyugQ3AAUhoAABFJhHU1VC2S4PXQAGefAAACh6T1MvMo9HiRwAAAFYAAAAYGNtYXC7LmevAAA+aAAAMdhnbHlmqrJbsgAArPQAA8GIaGVhZCZDt0gAAADcAAAANmhoZWEMnBawAAABFAAAACRobXR4qFNIVQAAAbgAADywbG9jYR43xcIAAHBAAAA8tG1heHAPTwF/AAABOAAAACBuYW1lnSrC4QAEbnwAAAXOcG9zdHTCuXUABHRMAACoNwABAAAAAgNUlClImV8PPPUAAwPoAAAAAN2A0+cAAAAA4T2cgf18/nsKzwQ8AAIABgACAAEAAAAAAAEAAAQt/tsAAArw/Wf9ZwrPA+gA1QAAAAAAAAAAAAAAAA85AAEAAA85AS0AGABxAAYAAQAAAAAAAAAAAAAAAAAEAAEABAImAZAABQAAAooCWP/wAEsCigJYAEoBXgAyAUIAAAILBQIEBQQJAgTgAAL/QAAgHwgAACkAEAAAR09PRwCgAAD//wQt/tsAAARkAYsAAAGfAAAAAAIiAsoAAAAgAAQCWABeAAAAAAEEAAABBAAAAR4AOQHYAEEChgAWAjwAKwOFAB8C7gAoAQoAQQFTACgBUwAeAiEAHwI8ACsBHQAfAUIAHgEdADkBnQAHAjwAJAI8ADsCPAAmAjwAJgI8ABECPAAxAjwAIwI8ABsCPAAjAjwAIAEdADkBHQAfAjwAKwI8ACsCPAArAd0AAwOBADICsgAAAqAAWgJ9ADoC5ABaAjAAWgIlAFoC1AA6Av0AWgGFACABS/+2ApgAWgI1AFoDrwBaAy0AWgMcADoCdABaAxwAOgKUAFoCJwAuAkMAFAL0AFUCigAAA8cAAAKbAAACcAAAAkMAGAFLAEYBnQAGAUsAGQI8ABcBm//+AWoAKAJcACoCeQBOAgIALQJ5AC0CTwAtAYMAFAJ5AC0CkQBOATEASAEx/8ACbABOATEATgPWAE4CkQBOAmsALQJ5AE4CeQAtAcYATgHxAC0BsgAXApEASwI5AAADWAAKAkIABQI5AAAB6AAbAYoADwInAN4BigAoAjwAKwEEAAABHgA5AjwARgI8ACgCPAA3AjwAAwInAN4B5gA0Al8AiANAADEBfwAXAmcAKAI8ACsBQgAeA0AAMQH0//0BrAAnAjwAKwF7ABcBewAdAWoAKAKUAE4CjwA3AR0AOQDN/+4BewAtAYQAHAJnACgDPgAWA2oAFgNNACwB3QAbArIAAAKyAAACsgAAArIAAAKyAAACsgAAA7gAAAJ9ADoCMABaAjAAWgIwAFkCMABaAYUADgGFACABhf/vAYUAGwLkABcDLQBaAxwAOgMcADoDHAA6AxwAOgMcADoCPAA/AxwAOgL0AFUC9ABVAvQAVQL0AFUCcAAAAnQAWgLHAE4CXAAqAlwAKgJcACoCXAAqAlwAKgJcACoDlQAqAgIALQJPAC0CTwAtAk8ALQJPAC0BMf/kATEATgEx/8UBMf/xAmsALQKRAE4CawAtAmsALQJrAC0CawAtAmsALQI8ACsCawAtApEASwKRAEsCkQBLApEASwI5AAACeQBOAjkAAAKyAAACXAAqArIAAAJcACoCsgAAAlwAKgJ9ADoCAgAtAn0AOgICAC0CfQA6AgIALQJ9ADoCAgAtAuQAWgJ5AC0C5AAXAokALQIwAFoCTwAtAjAAWgJPAC0CMABaAk8ALQIwAFoCTwAtAjAAWQJPAC0C1AA6AnkALQLUADoCeQAtAtQAOgJ5AC0C1AA6AnkALQL9AFoCkf/HAv0AAAKRAAIBhf/5ATH/zwGFAB0BMf/zAYUAAwEx/9kBhQAgATEALQGFACAC0AAgAmIASAFL/7YBMf/AApgAWgJsAE4CbABOAjUAWgExAE4CNQBaATEARQI1AFoBMQBOAjUAWgFq
    format('truetype');
    font-weight: 700;
    font-style: normal;
}
        @font-face {
    font-family: 'NotoSans';
    src: url('data:font/truetype;charset=utf-8;base64,AAEAAAANAIAAAwBQR0RFRv0u3/EABVooAAAFckdQT1PyZvLZAAVfnAABGlRHU1VC2S4PXQAGefAAACh6T1MvMo4WhvAAAAFYAAAAYGNtYXAgAbDVAAA+nAAAK5RnbHlmAExE2wAApxgABANvaGVhZCYOtDYAAADcAAAANmhoZWENOxpTAAABFAAAACRobXR4E7MnigAAAbgAADzkbG9jYR+38nMAAGowAAA86G1heHAPXAGgAAABOAAAACBuYW1liHWbMwAEqogAAAagcG9zdOs5rhsABLEoAACo/QABAAAAAgNUDboXpF8PPPUAAwPoAAAAAN2AzKIAAAAA4T2gpf1n/nsKzwQ8AAIABgACAAEAAAAAAAEAAAQt/tsAAArw/Wf9ZwrPA+gA1QAAAAAAAAAAAAAAAA85AAEAAA85AS0AGABxAAYAAQAAAAAAAAAAAAAAAAAEAAEABAImAZAABQAAAooCWP/wAEsCigJYAEoBXgAyAUIAAAILBQIEBQQJAgTgAAL/QAAgHwgAACkAEAAAR09PRwGBAAD//wQt/tsAAARkAYsAAAGfAAAAAAIYAsoAAAAgAAQCWABeAAAAAAEEAAABBAAAAQUAFAGIAG0ChgAeAicAIgMfAFACoQAgANwAbQEiACgBIv+zAicAZwI8AEYBAP/VATkAGgEAABQBZP/SAicAOQInAI8CJwADAicAFgInAAYCJwAlAicAPwInAFECJwAtAicALwEAABQBAP/PAjwARgI8AEwCPABGAa4ATQNQADUCMv/HAlgAKQJLAEgCmwApAgIAKQHdACkCpgBIAqgAKgFE/+wBEf9kAjIAKQHeACkDSQAoAsMAKALRAEgCNwApAtEASAI9ACkB+QATAfUAWgKlAE8CKABcA1gAawIP/8wB/QBcAhP/9gEi//cBZABsASL/uAI8ACYBi/+kARYAkgI4ADACQwAcAcUAMAJDADAB8wAwAT7/kAJDABkCQwAcAQIAHAEC/4IB7wAbAQIAGwNrABwCQwAYAjMAMAJD/+oCQwAwAY4AHAGwAAUBTAAsAkMANwHTADAC0wA5AeP/2wHT/6IBvf/xAV4ACwInAQQBXv/bAjwARgEEAAABBf/2AicAawIn//UCJwBQAicAPAInAQQB5gAbAioAywNAADEBeQBKAjIAIwI8ADUBQgAUA0AAMQH0AFcBrAAnAjwAKwF7ABcBewAdAWoAKAKUAE4CjwA3AR0AOQDN/+4BewAtAYQAHAJnACgDPgAWA2oAFgNNACwB3QAbArIAAAKyAAACsgAAArIAAAKyAAACsgAAA7gAAAJ9ADoCMABaAjAAWgIwAFkCMABaAYUADgGFACABhf/vAYUAGwLkABcDLQBaAxwAOgMcADoDHAA6AxwAOgMcADoCPAA/AxwAOgL0AFUC9ABVAvQAVQL0AFUCcAAAAnQAWgLHAE4CXAAqAlwAKgJcACoCXAAqAlwAKgJcACoDlQAqAgIALQJPAC0CTwAtAk8ALQJPAC0BMf/kATEATgEx/8UBMf/xAmsALQKRAE4CawAtAmsALQJrAC0CawAtAmsALQI8ACsCawAtApEASwKRAEsCkQBLApEASwI5AAACeQBOAjkAAAKyAAACXAAqArIAAAJcACoCsgAAAlwAKgJ9ADoCAgAtAn0AOgICAC0CfQA6AgIALQJ9ADoCAgAtAuQAWgJ5AC0C5AAXAokALQIwAFoCTwAtAjAAWgJPAC0CMABaAk8ALQIwAFoCTwAtAjAAWQJPAC0C1AA6AnkALQLUADoCeQAtAtQAOgJ5AC0C1AA6AnkALQL9AFoCkf/HAv0AAAKRAAIBhf/5ATH/zwGFAB0BMf/zAYUAAwEx/9kBhQAgATEALQGFACAC0AAgAmIASAFL/7YBMf/AApgAWgJsAE4CbABOAjUAWgExAE4CNQBaATEARQI1AFoBMQBOAjUAWgFq
    format('truetype');
    font-weight: 400;
    font-style: italic;
}

html, body {
    font-family: 'NotoSans', sans-serif;
    font-size: 11px;
    color: #0f172a;
}

table, th, td, p, div, span {
    font-family: 'NotoSans', sans-serif;
}
        table {
            width: 100%;
            border-collapse: collapse;
            outline: 3px solid #ff0000 !important;
            outline-offset: -3px;
        }

        th, td {
            border: 2px solid #ff0000 !important;
            padding: 8px;
            vertical-align: middle;
        }

        .title {
            font-size: 16px;
            font-weight: 700;
            text-align: center;
            background: #e2e8f0;
        }

        .meta {
            font-size: 10px;
            color: #334155;
            background: #f8fafc;
        }

        .section {
            font-weight: 700;
            background: #e2e8f0;
        }

        .head {
            background: #f1f5f9;
            font-weight: 700;
        }

        .center {
            text-align: center;
        }
    </style>
</head>

<body>
    <table>
        <tr>
            <th colspan="4" class="title">{{ $title }}</th>
        </tr>
        <tr>
            <th colspan="4" class="meta">Kỳ báo cáo: {{ $periodLabel }}</th>
        </tr>
        <tr>
            <th colspan="4" class="meta">Thời gian xuất: {{ $generatedAt }} | Người xuất: {{ $generatedBy }}</th>
        </tr>
    </table>

    <table>
        <tr>
            <th colspan="4" class="section">Tổng quan công ty</th>
        </tr>
        <tr>
            <th class="head">Chỉ số</th>
            <th class="head center">Giá trị</th>
            <th class="head">Chỉ số</th>
            <th class="head center">Giá trị</th>
        </tr>
        <tr>
            <td>Tổng dự án</td>
            <td class="center">{{ $projects['total'] ?? 0 }}</td>
            <td>Đang thực hiện</td>
            <td class="center">{{ $projects['running'] ?? 0 }}</td>
        </tr>
        <tr>
            <td>Tạm dừng</td>
            <td class="center">{{ $projects['paused'] ?? 0 }}</td>
            <td>Hoàn thành</td>
            <td class="center">{{ $projects['completed'] ?? 0 }}</td>
        </tr>
        <tr>
            <td>Tiến độ dự án trung bình</td>
            <td class="center">{{ number_format((float) ($projects['avg_progress'] ?? 0), 2) }}%</td>
            <td>Tổng công việc</td>
            <td class="center">{{ $tasks['total'] ?? 0 }}</td>
        </tr>
        <tr>
            <td>Đang xử lý</td>
            <td class="center">{{ $tasks['in_progress'] ?? 0 }}</td>
            <td>Chờ phê duyệt</td>
            <td class="center">{{ $tasks['waiting_approval'] ?? 0 }}</td>
        </tr>
        <tr>
            <td>Quá hạn</td>
            <td class="center">{{ $tasks['late'] ?? 0 }}</td>
            <td>Sắp đến hạn</td>
            <td class="center">{{ $tasks['due_soon'] ?? 0 }}</td>
        </tr>
        <tr>
            <td>Phase tổng</td>
            <td class="center">{{ $phases['total'] ?? 0 }}</td>
            <td>Phase đang hoạt động</td>
            <td class="center">{{ $phases['active'] ?? 0 }}</td>
        </tr>
    </table>

    <table>
        <tr>
            <th colspan="4" class="section">KPI tổng hợp</th>
        </tr>
        <tr>
            <th class="head">Kỳ KPI</th>
            <th class="head center">Điểm</th>
            <th class="head center">Tỷ lệ đúng hạn</th>
            <th class="head center">Tỷ lệ SLA</th>
        </tr>
        <tr>
            <td>KPI tháng</td>
            <td class="center">{{ number_format((float) ($kpiMonthly['final_score'] ?? 0), 1) }}</td>
            <td class="center">{{ number_format((float) ($kpiMonthly['on_time_rate'] ?? 0), 1) }}%</td>
            <td class="center">{{ number_format((float) ($kpiMonthly['sla_rate'] ?? 0), 1) }}%</td>
        </tr>
        <tr>
            <td>KPI quý</td>
            <td class="center">{{ number_format((float) ($kpiQuarterly['final_score'] ?? 0), 1) }}</td>
            <td class="center">{{ number_format((float) ($kpiQuarterly['on_time_rate'] ?? 0), 1) }}%</td>
            <td class="center">{{ number_format((float) ($kpiQuarterly['sla_rate'] ?? 0), 1) }}%</td>
        </tr>
    </table>

    <table>
        <tr>
            <th colspan="3" class="section">Top hiệu suất</th>
        </tr>
        <tr>
            <th class="head">Nhân sự</th>
            <th class="head center">Điểm</th>
            <th class="head center">SLA / Đúng hạn</th>
        </tr>
        @forelse ($topPerformers as $performer)
            <tr>
                <td>{{ $performer['user_name'] ?? '—' }}</td>
                <td class="center">{{ number_format((float) ($performer['final_score'] ?? 0), 1) }}</td>
                <td class="center">
                    {{ number_format((float) ($performer['sla_rate'] ?? 0), 1) }}% / {{ number_format((float) ($performer['on_time_rate'] ?? 0), 1) }}%
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="3" class="center">Chưa có dữ liệu top performer.</td>
            </tr>
        @endforelse
    </table>

    <table>
        <tr>
            <th colspan="4" class="section">Công việc gần đây</th>
        </tr>
        <tr>
            <th class="head">Công việc</th>
            <th class="head">Dự án / Phase</th>
            <th class="head center">PIC</th>
            <th class="head center">Deadline</th>
        </tr>
        @forelse ($recentTasks->take(10) as $task)
            <tr>
                <td>{{ $task->name }}</td>
                <td>{{ $task->phase?->project?->name ?? 'N/A' }} / {{ $task->phase?->name ?? 'N/A' }}</td>
                <td class="center">{{ $task->pic?->name ?? 'N/A' }}</td>
                <td class="center">{{ $task->deadline?->format('d/m/Y') ?? '—' }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="4" class="center">Không có dữ liệu task gần đây.</td>
            </tr>
        @endforelse
    </table>

    <table>
        <tr>
            <th colspan="5" class="section">Task chờ phê duyệt</th>
        </tr>
        <tr>
            <th class="head">Công việc</th>
            <th class="head">Dự án / Phase</th>
            <th class="head center">PIC</th>
            <th class="head center">Tiến độ</th>
            <th class="head center">Deadline</th>
        </tr>
        @forelse ($approvalTasks->take(10) as $task)
            <tr>
                <td>{{ $task->name }}</td>
                <td>{{ $task->phase?->project?->name ?? 'N/A' }} / {{ $task->phase?->name ?? 'N/A' }}</td>
                <td class="center">{{ $task->pic?->name ?? 'N/A' }}</td>
                <td class="center">{{ (int) $task->progress }}%</td>
                <td class="center">{{ $task->deadline?->format('d/m/Y') ?? '—' }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="5" class="center">Không có task chờ phê duyệt.</td>
            </tr>
        @endforelse
    </table>
</body>

</html>